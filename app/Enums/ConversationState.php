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
