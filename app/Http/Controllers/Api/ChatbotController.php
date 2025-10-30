<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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
        try {
            $entries = $request->input('entry', []);

            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];

                foreach ($changes as $change) {
                    if (($change['field'] ?? null) !== 'messages') {
                        continue;
                    }

                    $value = $change['value'] ?? [];

                    if (($value['messaging_product'] ?? null) !== 'whatsapp') {
                        continue;
                    }

                    $messages = $value['messages'] ?? [];

                    foreach ($messages as $message) {
                        if (($message['type'] ?? null) !== 'text') {
                            continue;
                        }

                        $phone = $message['from'] ?? null;
                        $text  = data_get($message, 'text.body');

                        if ($phone && filled($text)) {
                            try {
                               $response = Http::timeout(8)
                                    ->acceptJson()
                                    ->post(
                                        config('services.whatsapp.forward_url', 'http://127.0.0.1:8000/api/chat'),
                                        [
                                            'phone' => $phone,
                                            'message'  => $text,
                                        ]
                                    );

                                $replyText = data_get($response->json(), 'data.response') ?? data_get($response->json(), 'data');

                                $this->sendWhatsAppMessage($phone, $replyText ?? 'Obrigado por sua mensagem! Em breve retornaremos.');
                                
                            } catch (\Throwable $e) {
                                Log::warning('Falha ao encaminhar para /api/chat', [
                                    'error' => $e->getMessage(),
                                    'phone' => $phone,
                                ]);
                            }
                        }
                    }
                }
            }

            // Sempre 200 para o Meta não ficar reenviando sem necessidade
            return response()->json(['success' => true], 200);

        } catch (\Throwable $e) {
            Log::error('Erro ao processar webhook do WhatsApp', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            // Ainda assim, responder 200 evita tempestade de retries.
            return response()->json(['success' => false], 200);
        }
    }

    public function verifyWebhook(Request $request): mixed
    {
        $mode      = $request->query('hub_mode', $request->input('hub.mode'));
        $token     = $request->query('hub_verify_token', $request->input('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->input('hub.challenge'));

        if (strtolower((string)$mode) === 'subscribe'
            && (string)$token === (string) config('services.whatsapp.verify_token')) {
            return response((string)$challenge, 200)->header('Content-Type', 'text/plain');
        }
        \Log::info('WEBHOOK VERIFY', compact('mode','token','challenge'));
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
                    'requires_attention' => null,
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
        $graphVersion      = config('services.whatsapp.graph_version', 'v22.0');
        $phoneNumberId     = config('services.whatsapp.phone_number_id');
        $accessToken       = config('services.whatsapp.access_token', config('services.whatsapp.api_token'));

        if (!$phoneNumberId || !$accessToken) {
            Log::error('WhatsApp config ausente: phone_number_id ou access_token/api_token não configurados.');
            return;
        }

        $endpoint = "https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $message,
            ],
        ];

        try {
            $res = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            if (!$res->successful()) {
                Log::error('Erro ao enviar mensagem WhatsApp', [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                    'to'     => $phone,
                ]);
            } else {
                Log::info('Mensagem WhatsApp enviada com sucesso', [
                    'to'     => $phone,
                    'wa_msg' => $res->json(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Exceção ao enviar mensagem WhatsApp', [
                'error' => $e->getMessage(),
                'to'    => $phone,
            ]);
        }
    }
}
