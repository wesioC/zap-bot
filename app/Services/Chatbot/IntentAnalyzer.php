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

    // analisa a intenção da mensagem do cliente com regras simples
    private function analyzeByRules(string $message): CustomerIntent
    {
        $messageLower = mb_strtolower($message);

        if (preg_match('/^(oi|olá|ola|bom dia|boa tarde|boa noite|e aí|eai|salve)/i', $messageLower)) {
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

        if (preg_match('/\b(vaquejada|comitiva|esportivo|futebol|ciclismo|corporativo|escolar|promocional|academia|uv\s*50|dry\s*fit)\b/i', $messageLower)) {
            return CustomerIntent::PROVIDING_CATEGORY;
        }

        if( preg_match('/\b(pernambuco|pe)\b/i', $messageLower)) {
            return CustomerIntent::IS_PE;
        }

        if( preg_match('/\b(acre|ac|alagoas|al|amapa|ap|amazonas|am|bahia|ba|ceara|ce|distrito federal|df|espirito santo|es|goias|go|maranhao|ma|mato grosso|mt|mato grosso do sul|ms|minas gerais|mg|para|pa|paraiba|pb|parana|pr|piaui|pi|rio de janeiro|rj|rio grande do norte|rn|rio grande do sul|rs|rondonia|ro|roraima|rr|santa catarina|sc|sao paulo|sp|sergipe|se|tocantins|to)\b/i', $messageLower)) {
            return CustomerIntent::IS_BR;
        }

        if (preg_match('#https?://\S+#i', $messageLower)) {
            return CustomerIntent::PROVIDING_MODEL_LINK;
        }

        if (preg_match('/\b(\d{1,4})\b\s*(unid(?:ades)?|pcs?|peças|camisas|shirts|uniformes|conjuntos|camisa com short|camisas e shorts)?/iu', $messageLower)) {
            return CustomerIntent::PROVIDING_QUANTITY;
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
            - is_pe: Cliente é de uma cidade de do estado de Pernambuco (PE)
            - is_br: Cliente é de uma cidade de outro estado do Brasil (fora de PE)
            - providing_category: Fornecendo categoria/tipo de uniforme (vaquejada, futebol, corrida ou empresarial)
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
