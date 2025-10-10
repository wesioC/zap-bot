<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Conversation;
use App\Enums\ConversationState;
use App\Enums\CustomerIntent;
use App\Services\Chatbot\IntentAnalyzer;
use App\Services\Chatbot\StateManager;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Carbon\Carbon;

class ChatbotService
{
    public function __construct(
        private IntentAnalyzer $intentAnalyzer,
        private StateManager $stateManager
    ) {}

    public function processMessage(string $phone, string $message): array
    {
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            ['name' => null, 'has_design' => false]
        );

        $conversation = $customer->getOrCreateActiveConversation();
        $conversation->addMessage('user', $message);

        $intent = $this->intentAnalyzer->analyze($message, $conversation);
        $currentState = $this->stateManager->getCurrentState($conversation);
        $nextState = $this->stateManager->getNextState($currentState, $intent, $conversation);

        $aiResponse = $this->generateResponseForState($customer, $conversation, $currentState, $intent, $message);

        $this->stateManager->updateConversationState($conversation, $nextState);
        $conversation->addMessage('assistant', $aiResponse);

        \Log::info('Chatbot processou mensagem', [
            'customer_id' => $customer->id,
            'intent' => $intent->value,
            'state_from' => $currentState->value,
            'state_to' => $nextState->value,
        ]);

        return [
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'response' => $aiResponse,
            'status' => $conversation->status,
            'state' => $nextState->value,
            'intent' => $intent->value,
        ];
    }

    private function generateResponseForState(
        Customer $customer,
        Conversation $conversation,
        ConversationState $state,
        CustomerIntent $intent,
        string $userMessage
    ): string {
        $systemPrompt = $this->buildSystemPromptForState($customer, $state, $intent);
        $history = $this->getRecentHistory($conversation);

        $messages = [
            new SystemMessage($systemPrompt),
            ...$history,
            new UserMessage($userMessage),
        ];

        $maxRetries = config('chatbot.ai.max_retries', 3);
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $response = Prism::text()
                    ->using(Provider::OpenAI, config('chatbot.ai.model'))
                    ->withMessages($messages)
                    ->withMaxTokens(config('chatbot.ai.max_tokens'))
                    ->generate();

                return $response->text;

            } catch (\Exception $e) {
                $attempt++;
                $isRateLimit = str_contains($e->getMessage(), 'rate limit');

                \Log::warning("Tentativa {$attempt}/{$maxRetries} falhou", [
                    'error' => $e->getMessage(),
                    'state' => $state->value,
                    'is_rate_limit' => $isRateLimit,
                ]);

                if ($attempt >= $maxRetries || !$isRateLimit) {
                    \Log::error('Erro ao gerar resposta da IA', [
                        'error' => $e->getMessage(),
                        'state' => $state->value,
                        'intent' => $intent->value,
                    ]);

                    return $this->getFallbackResponse($state);
                }

                sleep(pow(2, $attempt));
            }
        }

        return $this->getFallbackResponse($state);
    }

    private function buildSystemPromptForState(Customer $customer, ConversationState $state, CustomerIntent $intent): string
    {
        $greeting = $this->getGreeting();
        $storeName = config('chatbot.store_name');
        $deliveryMin = config('chatbot.business.delivery_time_min');
        $deliveryMax = config('chatbot.business.delivery_time_max');
        $deliveryUnit = config('chatbot.business.delivery_time_unit');

        $baseInstructions = "Você é um assistente virtual de uma loja de uniformes profissionais. Seja cordial, natural e profissional.";

        $stateInstructions = match($state) {
            ConversationState::GREETING => "
                SEMPRE comece com '{$greeting}!'.
                Depois se apresente: 'Sou o assistente virtual da {$storeName} de uniformes profissionais'.
                Por fim, pergunte: 'Você já possui um design/arte para o seu uniforme?'
                IMPORTANTE: Use EXATAMENTE essa estrutura: Saudação + Apresentação + Pergunta
            ",

            ConversationState::ASKING_DESIGN => "
                O cliente ainda não respondeu claramente se tem design.
                Reformule a pergunta de forma amigável: 'Você já tem o design/arte do uniforme pronto?'
            ",

            ConversationState::HAS_DESIGN => "
                Ótimo! O cliente tem design.
                Diga que é excelente e que você vai encaminhar para o setor financeiro preparar um orçamento personalizado.
                Pergunte se o cliente gostaria de informar mais detalhes (quantidade, tipo de uniforme, prazo desejado).
            ",

            ConversationState::NO_DESIGN => "
                O cliente não tem design ainda.
                Tranquilize o cliente dizendo que isso não é problema.
                Explique brevemente que vocês oferecem serviço de criação de design personalizado.
                Pergunte se gostaria de saber mais sobre o serviço de criação.
            ",

            ConversationState::OFFERING_DESIGN_SERVICE => "
                Explique o serviço de criação de design:
                - Equipe de designers especializados
                - Design personalizado conforme necessidade
                - Processo colaborativo com aprovação do cliente
                Pergunte se o cliente tem interesse em contratar o serviço.
            ",

            ConversationState::COLLECTING_DETAILS => "
                Colete informações importantes:
                - Tipo de uniforme (camiseta, calça, jaleco, etc)
                - Quantidade aproximada
                - Cores desejadas
                - Tamanhos necessários
                - Prazo desejado
                Faça perguntas uma de cada vez para não sobrecarregar.
            ",

            ConversationState::WAITING_BUDGET => "
                Informe que a solicitação foi encaminhada e que em breve a equipe entrará em contato com o orçamento.
                Pergunte se há mais alguma dúvida.
            ",

            ConversationState::ANSWERING_QUESTIONS => match($intent) {
                CustomerIntent::ASKING_DELIVERY_TIME => "
                    Informe que os prazos variam de {$deliveryMin} a {$deliveryMax} {$deliveryUnit} após aprovação do pedido, dependendo da quantidade.
                    Para prazo exato, um especialista pode ajudar.
                ",
                CustomerIntent::ASKING_PRICE => "
                    Explique que os valores dependem de: quantidade, tipo de uniforme, complexidade do design.
                    Sugira fazer um orçamento personalizado sem compromisso.
                ",
                CustomerIntent::ASKING_PRODUCTS => "
                    Informe que trabalham com diversos tipos: camisetas, polos, jalecos, calças, aventais, bonés, etc.
                    Pergunte qual tipo interessa ao cliente.
                ",
                default => "Responda a pergunta de forma objetiva e profissional.",
            },

            default => "Responda de forma educada e profissional, mantendo o contexto da conversa.",
        };

        return "{$baseInstructions}\n\nEstado atual: {$state->description()}\nIntenção detectada: {$intent->description()}\n\n{$stateInstructions}\n\nIMPORTANTE: Seja natural, use linguagem brasileira e mantenha respostas concisas (2-4 frases).";
    }

    private function getRecentHistory(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(config('chatbot.history_limit', 10))
            ->get()
            ->reverse();

        $result = [];
        foreach ($messages as $msg) {
            if ($msg->role === 'user') {
                $result[] = new UserMessage($msg->content);
            } elseif ($msg->role === 'assistant') {
                $result[] = new AssistantMessage($msg->content);
            }
        }

        return $result;
    }

    private function getFallbackResponse(ConversationState $state): string
    {
        return match($state) {
            ConversationState::GREETING => "Olá! Bem-vindo à nossa loja de uniformes. Como posso ajudá-lo hoje?",
            ConversationState::ASKING_DESIGN => "Você já possui um design/arte para o uniforme?",
            ConversationState::HAS_DESIGN => "Perfeito! Vou encaminhar para nossa equipe preparar um orçamento.",
            ConversationState::NO_DESIGN => "Sem problemas! Oferecemos serviço de criação de design. Gostaria de saber mais?",
            default => config('chatbot.messages.error', 'Desculpe, estou com dificuldades técnicas. Por favor, aguarde um momento.'),
        };
    }

    private function getGreeting(): string
    {
        $hour = Carbon::now()->hour;

        if ($hour >= 5 && $hour < 12) {
            return 'Bom dia';
        } elseif ($hour >= 12 && $hour < 18) {
            return 'Boa tarde';
        }

        return 'Boa noite';
    }

    public function updateCustomerHasDesign(int $customerId, bool $hasDesign): void
    {
        Customer::find($customerId)?->update(['has_design' => $hasDesign]);
    }
}
