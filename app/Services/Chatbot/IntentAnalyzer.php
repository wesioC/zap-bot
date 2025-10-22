<?php

namespace App\Services\Chatbot;

use App\Enums\CustomerIntent;
use App\Enums\ConversationState;
use App\Models\Conversation;
use App\Services\Chatbot\StateManager; 
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class IntentAnalyzer
{
    public function __construct(
        private StateManager $stateManager   
    ) {}

    public function analyze(string $message, ?Conversation $conversation = null): CustomerIntent
    {
        $state = $conversation
            ? $this->stateManager->getCurrentState($conversation)
            : ConversationState::GREETING;

        $intentByRules = $this->analyzeByRules($message, $state);

        if ($intentByRules !== CustomerIntent::UNCLEAR) {
            return $intentByRules;
        }

        return $this->analyzeByAI($message, $conversation);
    }

    // analisa a intenção da mensagem do cliente com regras simples
    private function analyzeByRules(string $message, ConversationState $state): CustomerIntent
    {
        $messageLower = mb_strtolower($message);

        if (preg_match('/^(oi|olá|ola|bom dia|boa tarde|boa noite|e aí|eai|salve)\b/iu', $messageLower)) {
            return CustomerIntent::GREETING;
        }

        // Perguntas de prazo/preço: sempre disponíveis
        if (preg_match('/\b(prazo|quanto tempo|demora|entrega|entregar)\b/iu', $messageLower)) {
            return CustomerIntent::ASKING_DELIVERY_TIME;
        }
        if (preg_match('/\b(preço|preco|valor|quanto custa|custa|orçamento|orcamento)\b/iu', $messageLower)) {
            return CustomerIntent::ASKING_PRICE;
        }

        // Link de modelo
        if (preg_match('#https?://\S+#iu', $messageLower)) {
            return CustomerIntent::PROVIDING_MODEL_LINK;
        }

        if (preg_match('/\b(j[áa]\s*tenho\s*(a\s*)?(arte|modelo|mockup|arquivo)|tenho\s*(a\s*)?(arte|modelo|mockup|arquivo)|arte\s*pronta|modelo\s*pronto|temos\s*arte)\b/iu', $messageLower)) {
            if (in_array($state, [
                ConversationState::ASKING_DESIGN,
                ConversationState::SHOWING_CATEGORY_CATALOG,
                ConversationState::ASKING_CATEGORY,
                ConversationState::GREETING, 
            ], true)) {
                return CustomerIntent::HAS_DESIGN_YES;
            }
            return CustomerIntent::CONFIRMATION_YES;
        }

        // Categoria (bem abrangente)
        if (preg_match('/\b(vaque(i)?jada|comitiva|esportivo|futebol|corrida|corporativo|empresarial|jaleco|avent(al|ais)|bon(é|e)s?|uv\s*50|dry\s*fit|polo|camis(et|a))\b/iu', $messageLower)) {
            return CustomerIntent::PROVIDING_CATEGORY;
        }

        // Estado
        // if (preg_match('/\b(pernambuco|^pe$|\bpe\b)\b/iu', $messageLower)) {
        //     return CustomerIntent::IS_PE;
        // }
        // if (preg_match('/\b(ac|al|ap|am|ba|ce|df|es|go|ma|mt|ms|mg|pa|pb|pr|pi|rj|rn|rs|ro|rr|sc|sp|se|to|acre|alagoas|amap[aá]|amazonas|bahia|cear[aá]|distrito federal|esp[íi]rito santo|goi[aá]s|maranh[aã]o|mato grosso do sul|mato grosso|minas gerais|par[aá]|para[ií]ba|paran[aá]|pia[uí]|rio de janeiro|rio grande do norte|rio grande do sul|rond[oô]nia|roraima|santa catarina|s[aã]o paulo|sergipe|tocantins)\b/iu', $messageLower)) {
        //     return CustomerIntent::IS_BR;
        // }

        // Confirmação SÓ vira "tem design" se estivermos perguntando disso
        if (preg_match('/\b(sim|tenho|possuo|j[aá]\s*tenho|claro|com certeza|ok|certo)\b/iu', $messageLower)) {
            if (in_array($state, [
                ConversationState::ASKING_DESIGN,
                ConversationState::SHOWING_CATEGORY_CATALOG, // “já tenho modelo”
            ], true)) {
                return CustomerIntent::HAS_DESIGN_YES;
            }
            return CustomerIntent::CONFIRMATION_YES;
        }

        if (preg_match('/\b(n[aã]o( tenho)?|negativo|ainda n[aã]o)\b/iu', $messageLower)) {
            if ($state === ConversationState::ASKING_DESIGN) {
                return CustomerIntent::HAS_DESIGN_NO;
            }
            return CustomerIntent::CONFIRMATION_NO;
        }

        // Quantidade: aceite número “solto” apenas SE estamos perguntando a quantidade
        if (preg_match('/\b(\d{1,4})\b\s*(unid(?:ades)?|pcs?|pe[cç]as|camisas|uniformes|conjuntos)?\b/iu', $messageLower)) {
            if (in_array($state, [ConversationState::ASKING_QUANTITY, ConversationState::HAS_DESIGN], true)) {
                return CustomerIntent::PROVIDING_QUANTITY;
            }
        }

        if (preg_match('/\b(obrigad|valeu|agradec[oa])\b/iu', $messageLower)) {
            return CustomerIntent::THANKING;
        }
        if (preg_match('/\b(tchau|adeus|at[eé] logo|falou|flw)\b/iu', $messageLower)) {
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
            - will_provide_design: Cliente vai providenciar o design depois
            - providing_quantity: Fornecendo quantidade de uniformes
            - providing_model_link: Enviando link de modelo/exemplo de uniforme
            - asking_delivery_time: Perguntando sobre prazo de entrega
            - asking_price: Perguntando sobre preços/valores
            - is_pe: Cliente é de uma cidade de do estado de Pernambuco (PE)
            - is_br: Cliente é de uma cidade de outro estado do Brasil (fora de PE)
            - providing_category: Fornecendo categoria/tipo de uniforme (vaquejada, futebol, corrida ou empresarial)
            - asking_products: Perguntando sobre produtos/tipos de uniforme
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
