<?php

namespace App\Services\Chatbot;

use App\Enums\ConversationState;
use App\Enums\CustomerIntent;
use App\Models\Conversation;
use App\Models\Customer;

class StateManager
{
    public function getNextState(
        ConversationState $currentState,
        CustomerIntent $intent,
        Conversation $conversation
    ): ConversationState {
        return match($currentState) {
            ConversationState::GREETING => $this->fromGreeting($intent),
            ConversationState::HAS_DESIGN => $this->fromHasDesign($intent),
            ConversationState::NO_DESIGN => $this->fromNoDesign($intent),
            ConversationState::ASKING_QUANTITY => $this->fromAskingQuantity($intent, $conversation),
            ConversationState::ASKING_CATEGORY => $this->fromAskingCategory($intent),
            ConversationState::ASKING_STATE => $this->fromAskingState($intent),
            ConversationState::SENDING_TO_FINANCIAL_BR => $this->fromSendingToFinancialBR($intent),
            ConversationState::SENDING_TO_FINANCIAL_PE => $this->fromSendingToFinancialPE($intent),
            ConversationState::SHOWING_CATEGORY_CATALOG => $this->fromShowingCategoryCatalog($intent),
            ConversationState::ANSWERING_QUESTIONS => $this->fromAnsweringQuestions($intent, $currentState),
            default => $currentState,
        };
    }

    // private function fromGreeting(CustomerIntent $intent): ConversationState
    // {
    //     return match($intent) {
    //         CustomerIntent::GREETING => ConversationState::ASKING_CATEGORY,
    //         CustomerIntent::PROVIDING_CATEGORY => ConversationState::SHOWING_CATEGORY_CATALOG,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE,
    //         CustomerIntent::ASKING_PRODUCTS => ConversationState::ANSWERING_QUESTIONS,
    //         default => ConversationState::ASKING_CATEGORY,
    //     };
    // }

    // private function fromAskingDesign(CustomerIntent $intent, Conversation $conversation): ConversationState
    // {
    //     return match($intent) {
    //         CustomerIntent::HAS_DESIGN_YES,
    //         CustomerIntent::CONFIRMATION_YES => $this->handleHasDesignYes($conversation),
    //         CustomerIntent::HAS_DESIGN_NO => ConversationState::NO_DESIGN,
    //         CustomerIntent::CONFIRMATION_NO => ConversationState::NO_DESIGN,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE,
    //         CustomerIntent::ASKING_PRODUCTS => ConversationState::ANSWERING_QUESTIONS,
    //         default => ConversationState::ASKING_DESIGN, // Mantém perguntando
    //     };
    // }

    // private function fromAskingCategory(CustomerIntent $intent): ConversationState
    // {
    //     return match ($intent) {
    //         CustomerIntent::GREETING => ConversationState::ASKING_CATEGORY,
    //         CustomerIntent::PROVIDING_CATEGORY => ConversationState::SHOWING_CATEGORY_CATALOG,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
    //         default                          => ConversationState::SHOWING_CATEGORY_CATALOG, 
    //     };
    // }

    // private function fromShowingCategoryCatalog(CustomerIntent $intent): ConversationState
    // {
    //     return match ($intent) {
    //         CustomerIntent::PROVIDING_MODEL_LINK => ConversationState::ASKING_QUANTITY,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
    //         default => ConversationState::SHOWING_CATEGORY_CATALOG,
           
    //     };
    // }

    // private function fromAskingQuantity(CustomerIntent $intent, Conversation $conversation): ConversationState
    // {
    //     return match ($intent) {
    //         CustomerIntent::PROVIDING_QUANTITY => ConversationState::ASKING_STATE,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
    //         default => ConversationState::ASKING_QUANTITY,
    //     };
    // }

    // private function fromAskingState(CustomerIntent $intent): ConversationState
    // {
    //     return match ($intent) {
    //         CustomerIntent::IS_PE => ConversationState::SENDING_TO_FINANCIAL_PE,
    //         CustomerIntent::IS_BR => ConversationState::SENDING_TO_FINANCIAL_BR,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
    //         default => ConversationState::ASKING_STATE,
    //     };
    // }
    // private function fromSendingToFinancialBR(CustomerIntent $intent): ConversationState
    // {
    //     return match ($intent) {
    //         CustomerIntent::IS_PE => ConversationState::SENDING_TO_FINANCIAL_PE,
    //         CustomerIntent::IS_BR => ConversationState::SENDING_TO_FINANCIAL_BR,
    //         CustomerIntent::GOODBYE => ConversationState::COMPLETED,
    //         CustomerIntent::THANKING => ConversationState::COMPLETED,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
    //         default => ConversationState::COMPLETED,
    //     };
    // }
    // private function fromSendingToFinancialPE(CustomerIntent $intent): ConversationState
    // {
    //     return match ($intent) {
    //         CustomerIntent::IS_PE => ConversationState::SENDING_TO_FINANCIAL_PE,
    //         CustomerIntent::IS_BR => ConversationState::SENDING_TO_FINANCIAL_BR,
    //         CustomerIntent::GOODBYE => ConversationState::COMPLETED,
    //         CustomerIntent::THANKING => ConversationState::COMPLETED,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
    //         default => ConversationState::COMPLETED,
    //     };
    // }
    // private function fromHasDesign(CustomerIntent $intent): ConversationState
    // {
    //     return match($intent) {
    //         CustomerIntent::PROVIDING_QUANTITY => ConversationState::ASKING_STATE,
    //         CustomerIntent::CONFIRMATION_YES => ConversationState::ASKING_QUANTITY,
    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
    //         default => ConversationState::ASKING_QUANTITY,
    //     };
    // }

    // private function fromNoDesign(CustomerIntent $intent): ConversationState
    // {
    //     return match ($intent) {
    //         CustomerIntent::PROVIDING_CATEGORY => ConversationState::SHOWING_CATEGORY_CATALOG,
    //         CustomerIntent::CONFIRMATION_YES => ConversationState::ASKING_QUANTITY,
    //         CustomerIntent::CONFIRMATION_NO ,  
    //             => $this->fromAskingCategory($intent),

    //         CustomerIntent::ASKING_DELIVERY_TIME,
    //         CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,

    //         default => ConversationState::ASKING_CATEGORY,
    //     };
    // }



    private function fromGreeting(CustomerIntent $intent): ConversationState
    {
        return match($intent) {
            CustomerIntent::GREETING            => ConversationState::ASKING_CATEGORY,
            CustomerIntent::PROVIDING_CATEGORY  => ConversationState::SHOWING_CATEGORY_CATALOG,
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE,
            CustomerIntent::ASKING_PRODUCTS     => ConversationState::ANSWERING_QUESTIONS,
            default                             => ConversationState::ASKING_CATEGORY,
        };
    }

    private function fromAskingCategory(CustomerIntent $intent): ConversationState
    {
        return match ($intent) {
            CustomerIntent::PROVIDING_CATEGORY  => ConversationState::SHOWING_CATEGORY_CATALOG,
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE        => ConversationState::ANSWERING_QUESTIONS,
            default                             => ConversationState::ASKING_CATEGORY,
        };
    }

    private function fromShowingCategoryCatalog(CustomerIntent $intent): ConversationState
    {
        return match ($intent) {
            // Link enviado OU disse que já tem modelo → perguntar quantidade
            CustomerIntent::PROVIDING_CATEGORY => ConversationState::SHOWING_CATEGORY_CATALOG,
            CustomerIntent::PROVIDING_MODEL_LINK => ConversationState::ASKING_QUANTITY,
            CustomerIntent::HAS_DESIGN_YES     => ConversationState::ASKING_QUANTITY,

            // Se voltar a perguntar preço/prazo
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE        => ConversationState::ANSWERING_QUESTIONS,

            // Sem link ainda → continue oferecendo o link da categoria
            default                             => ConversationState::SHOWING_CATEGORY_CATALOG,
        };
    }

    private function fromAskingQuantity(CustomerIntent $intent, Conversation $conversation): ConversationState
    {
        return match ($intent) {
            CustomerIntent::PROVIDING_QUANTITY  => ConversationState::ASKING_STATE,
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE        => ConversationState::ANSWERING_QUESTIONS,
            default                             => ConversationState::ASKING_QUANTITY,
        };
    }

    private function fromAskingState(CustomerIntent $intent): ConversationState
    {
        return match ($intent) {
            CustomerIntent::PROVIDING_QUANTITY  => ConversationState::ASKING_STATE,
            CustomerIntent::IS_PE               => ConversationState::SENDING_TO_FINANCIAL_PE,
            CustomerIntent::IS_BR               => ConversationState::SENDING_TO_FINANCIAL_BR,
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE        => ConversationState::ANSWERING_QUESTIONS,
            default                             => ConversationState::ASKING_STATE,
        };
    }

    private function fromSendingToFinancialBR(CustomerIntent $intent): ConversationState
    {
        return match ($intent) {
            CustomerIntent::GOODBYE,
            CustomerIntent::THANKING            => ConversationState::COMPLETED,
            default                             => ConversationState::COMPLETED,
        };
    }

    private function fromSendingToFinancialPE(CustomerIntent $intent): ConversationState
    {
        return match ($intent) {
            CustomerIntent::GOODBYE,
            CustomerIntent::THANKING            => ConversationState::COMPLETED,
            default                             => ConversationState::COMPLETED,
        };
    }

    private function fromHasDesign(CustomerIntent $intent): ConversationState
    {
        // Se tem design, o próximo passo no fluxo é QUANTIDADE
        return match($intent) {
            CustomerIntent::PROVIDING_QUANTITY  => ConversationState::ASKING_STATE,
            default                             => ConversationState::ASKING_QUANTITY,
        };
    }

    private function fromNoDesign(CustomerIntent $intent): ConversationState
    {
        return match ($intent) {
            CustomerIntent::PROVIDING_CATEGORY  => ConversationState::SHOWING_CATEGORY_CATALOG,
            CustomerIntent::CONFIRMATION_YES    => ConversationState::ASKING_QUANTITY,
            CustomerIntent::CONFIRMATION_NO     => ConversationState::ASKING_CATEGORY,

            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE        => ConversationState::ANSWERING_QUESTIONS,

            default                             => ConversationState::ASKING_CATEGORY,
        };
    }

    private function fromAnsweringQuestions(CustomerIntent $intent, ConversationState $previousState): ConversationState
    {
        return match($intent) {
            CustomerIntent::HAS_DESIGN_YES      => ConversationState::HAS_DESIGN,
            CustomerIntent::HAS_DESIGN_NO       => ConversationState::NO_DESIGN,
            CustomerIntent::THANKING,
            CustomerIntent::GOODBYE             => ConversationState::COMPLETED,
            default                             => $previousState, 
        };
    }



    private function handleHasDesignYes(Conversation $conversation): ConversationState
    {
        $conversation->customer->update(['has_design' => true]);

        return ConversationState::HAS_DESIGN;
    }

    public function updateConversationState(Conversation $conversation, ConversationState $newState): void
    {
        $context = $conversation->context ?? [];
        $context['state'] = $newState->value;
        $context['state_history'] = $context['state_history'] ?? [];
        $context['state_history'][] = [
            'state' => $newState->value,
            'timestamp' => now()->toIso8601String(),
        ];

        $conversation->update([
            'status' => $this->mapStateToStatus($newState),
            'context' => $context,
        ]);
    }

    private function mapStateToStatus(ConversationState $state): string
    {
        return match($state) {
            ConversationState::COMPLETED => 'completed',
            ConversationState::ARCHIVED => 'archived',
            default => 'active',
        };
    }

    public function getCurrentState(Conversation $conversation): ConversationState
    {
        $context = $conversation->context ?? [];

        if (isset($context['state'])) {
            return ConversationState::from($context['state']);
        }

        return match($conversation->status) {
            'completed' => ConversationState::COMPLETED,
            'archived' => ConversationState::ARCHIVED,
            default => ConversationState::GREETING,
        };
    }
}
