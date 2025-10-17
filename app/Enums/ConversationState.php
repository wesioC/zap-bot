<?php

namespace App\Enums;

enum ConversationState: string
{
    case GREETING = 'greeting';

    case ASKING_DESIGN = 'asking_design';

    case HAS_DESIGN = 'has_design';

    case NO_DESIGN = 'no_design';

    case COLLECTING_DETAILS = 'collecting_details';

    case WAITING_DESIGN_FILE = 'waiting_design_file';

    case WAITING_BUDGET = 'waiting_budget';

    case OFFERING_DESIGN_SERVICE = 'offering_design_service';

    case WAITING_DESIGN_DECISION = 'waiting_design_decision';

    case ANSWERING_QUESTIONS = 'answering_questions';

    case ASKING_CATEGORY = 'asking_category';

    case ASKING_QUANTITY = 'asking_quantity';

    case ASKING_STATE = 'asking_state';

    case SHOWING_CATEGORY_CATALOG = 'showing_category_catalog';

    case SENDING_TO_FINANCIAL_BR = 'sending_to_financial_br';

    case SENDING_TO_FINANCIAL_PE = 'sending_to_financial_pe';

    case COMPLETED = 'completed';

    case ARCHIVED = 'archived';

    public function description(): string
    {
        return match($this) {
            self::GREETING => 'Saudação inicial',
            self::ASKING_DESIGN => 'Perguntando sobre design',
            self::HAS_DESIGN => 'Cliente tem design',
            self::NO_DESIGN => 'Cliente não tem design',
            self::COLLECTING_DETAILS => 'Coletando detalhes',
            self::WAITING_DESIGN_FILE => 'Aguardando arquivo',
            self::WAITING_BUDGET => 'Aguardando orçamento',
            self::OFFERING_DESIGN_SERVICE => 'Oferecendo serviço de design',
            self::ASKING_CATEGORY => 'Perguntando sobre categoria',
            self::ASKING_QUANTITY => 'Perguntando sobre quantidade',
            self::SENDING_TO_FINANCIAL_BR => 'Financeiro vai continuar daqui o atendimento',
            self::SENDING_TO_FINANCIAL_PE => 'Financeiro de Pernambuco vai chamar o cliente',
            self::ASKING_STATE => 'Perguntando sobre estado',
            self::SHOWING_CATEGORY_CATALOG => 'Mostrando catálogo de categorias',
            self::WAITING_DESIGN_DECISION => 'Aguardando decisão',
            self::ANSWERING_QUESTIONS => 'Respondendo perguntas',
            self::COMPLETED => 'Concluído',
            self::ARCHIVED => 'Arquivado',
        };
    }

    public function isActive(): bool
    {
        return !in_array($this, [self::COMPLETED, self::ARCHIVED]);
    }

    public function requiresHumanAttention(): bool
    {
        return in_array($this, [
            self::WAITING_BUDGET,
            self::WAITING_DESIGN_DECISION,
        ]);
    }
}
