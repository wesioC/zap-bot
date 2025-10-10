<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatbotController extends Controller
{
    public function __construct(
        private ChatbotService $chatbotService
    ) {}

    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:20',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->chatbotService->processMessage(
                $request->input('phone'),
                $request->input('message')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao processar mensagem do chatbot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar mensagem',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function whatsappWebhook(Request $request): JsonResponse
    {
        if ($request->input('hub_mode') === 'subscribe' &&
            $request->input('hub_verify_token') === config('services.whatsapp.verify_token')) {
            return response()->json([
                'hub_challenge' => $request->input('hub_challenge')
            ]);
        }

        try {
            $entries = $request->input('entry', []);

            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];

                foreach ($changes as $change) {
                    if ($change['field'] === 'messages') {
                        $messages = $change['value']['messages'] ?? [];

                        foreach ($messages as $message) {
                            if ($message['type'] === 'text') {
                                $phone = $message['from'];
                                $text = $message['text']['body'];

                                $result = $this->chatbotService->processMessage($phone, $text);

                                $this->sendWhatsAppMessage($phone, $result['response']);
                            }
                        }
                    }
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Erro ao processar webhook do WhatsApp', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar webhook',
            ], 500);
        }
    }

    public function verifyWebhook(Request $request): mixed
    {
        $mode = $request->input('hub.mode');
        $token = $request->input('hub.verify_token');
        $challenge = $request->input('hub.challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response()->json(['error' => 'Invalid verification token'], 403);
    }

    public function getConversationStatus($id): JsonResponse
    {
        try {
            $conversation = \App\Models\Conversation::with(['customer', 'messages'])
                ->findOrFail($id);

            $stateManager = new \App\Services\Chatbot\StateManager();
            $currentState = $stateManager->getCurrentState($conversation);

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation_id' => $conversation->id,
                    'customer' => [
                        'id' => $conversation->customer->id,
                        'phone' => $conversation->customer->phone,
                        'name' => $conversation->customer->name,
                        'has_design' => $conversation->customer->has_design,
                    ],
                    'state' => $currentState->value,
                    'state_description' => $currentState->description(),
                    'status' => $conversation->status,
                    'requires_attention' => $currentState->requiresHumanAttention(),
                    'message_count' => $conversation->messages->count(),
                    'last_message_at' => $conversation->last_message_at,
                    'created_at' => $conversation->created_at,
                    'state_history' => $conversation->context['state_history'] ?? [],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversa não encontrada',
            ], 404);
        }
    }

    public function getPendingConversations(): JsonResponse
    {
        $conversations = \App\Models\Conversation::with('customer')
            ->whereIn('status', ['waiting_budget', 'waiting_design'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $conversations->map(function ($conv) {
                $stateManager = new \App\Services\Chatbot\StateManager();
                $currentState = $stateManager->getCurrentState($conv);

                return [
                    'conversation_id' => $conv->id,
                    'customer_phone' => $conv->customer->phone,
                    'customer_name' => $conv->customer->name,
                    'state' => $currentState->value,
                    'state_description' => $currentState->description(),
                    'status' => $conv->status,
                    'last_message_at' => $conv->last_message_at,
                ];
            }),
        ]);
    }

    private function sendWhatsAppMessage(string $phone, string $message): void
    {
        \Log::info('Enviando mensagem WhatsApp', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
