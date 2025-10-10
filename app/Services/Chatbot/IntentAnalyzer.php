<?php

namespace App\Services\Chatbot;

use App\Enums\CustomerIntent;
use App\Models\Conversation;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class IntentAnalyzer
{
    public function analyze(string $message, ?Conversation $conversation = null): CustomerIntent
    {
        $intentByRules = $this->analyzeByRules($message);

        if ($intentByRules !== CustomerIntent::UNCLEAR) {
            return $intentByRules;
        }

        return $this->analyzeByAI($message, $conversation);
    }

    private function analyzeByRules(string $message): CustomerIntent
    {
        $messageLower = mb_strtolower($message);

        if (preg_match('/^(oi|olá|ola|bom dia|boa tarde|boa noite|e aí|eai)/i', $messageLower)) {
            return CustomerIntent::GREETING;
        }

        if (preg_match('/\b(sim|tenho|possuo|já tenho|ja tenho|claro|com certeza|ok|certo)\b/i', $messageLower)) {
            return CustomerIntent::CONFIRMATION_YES;
        }

        if (preg_match('/\b(não|nao|não tenho|nao tenho|negativo|ainda não|ainda nao)\b/i', $messageLower)) {
            return CustomerIntent::CONFIRMATION_NO;
        }

        if (preg_match('/\b(prazo|quanto tempo|demora|entrega|entregar)\b/i', $messageLower)) {
            return CustomerIntent::ASKING_DELIVERY_TIME;
        }

        if (preg_match('/\b(preço|preco|valor|quanto custa|custa|orçamento|orcamento)\b/i', $messageLower)) {
            return CustomerIntent::ASKING_PRICE;
        }

        if (preg_match('/\b(criar|criação|criacao|fazer|design|arte|logo)\b/i', $messageLower)) {
            return CustomerIntent::WANTS_DESIGN_CREATION;
        }

        if (preg_match('/\b(atendente|humano|pessoa|falar com alguém|falar com alguem)\b/i', $messageLower)) {
            return CustomerIntent::WANTS_HUMAN;
        }

        if (preg_match('/\b(obrigad|valeu|agradeço|agradeco|thanks)\b/i', $messageLower)) {
            return CustomerIntent::THANKING;
        }

        if (preg_match('/\b(tchau|adeus|até logo|ate logo|falou|flw)\b/i', $messageLower)) {
            return CustomerIntent::GOODBYE;
        }

        return CustomerIntent::UNCLEAR;
    }

    private function analyzeByAI(string $message, ?Conversation $conversation = null): CustomerIntent
    {
        $contextInfo = '';
        if ($conversation) {
            $lastMessages = $conversation->messages()
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->reverse()
                ->map(fn($m) => "{$m->role}: {$m->content}")
                ->join("\n");

            if ($lastMessages) {
                $contextInfo = "\n\nÚltimas mensagens da conversa:\n{$lastMessages}";
            }
        }

        $systemPrompt = <<<PROMPT
Você é um analisador de intenções para um chatbot de loja de uniformes.

Analise a mensagem do cliente e retorne APENAS uma das seguintes intenções (sem explicação):

- greeting: Saudação ou início de conversa
- has_design_yes: Cliente confirma que TEM design/arte
- has_design_no: Cliente confirma que NÃO tem design/arte
- wants_design_creation: Cliente quer criar/fazer um design
- will_provide_design: Cliente vai providenciar o design depois
- asking_delivery_time: Perguntando sobre prazo de entrega
- asking_price: Perguntando sobre preços/valores
- asking_products: Perguntando sobre produtos/tipos de uniforme
- providing_details: Fornecendo detalhes do pedido (quantidade, tamanhos, etc)
- wants_human: Quer falar com atendente humano
- goodbye: Despedida
- thanking: Agradecimento
- confirmation_yes: Confirmação positiva (sim, ok, correto)
- confirmation_no: Negação (não, negativo)
- unclear: Intenção não está clara
{$contextInfo}

IMPORTANTE: Responda APENAS com uma das palavras acima, sem pontuação ou explicação.
PROMPT;

        try {
            $response = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4o-mini')
                ->withMessages([
                    new SystemMessage($systemPrompt),
                    new UserMessage("Mensagem do cliente: {$message}"),
                ])
                ->withMaxTokens(20)
                ->generate();

            $intent = trim(strtolower($response->text));

            return CustomerIntent::tryFrom($intent) ?? CustomerIntent::UNCLEAR;

        } catch (\Exception $e) {
            \Log::warning('Erro ao analisar intenção com IA', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            return CustomerIntent::UNCLEAR;
        }
    }

    public function getConfidence(CustomerIntent $intent): int
    {
        if ($intent !== CustomerIntent::UNCLEAR) {
            return 90;
        }

        return 50;
    }
}
