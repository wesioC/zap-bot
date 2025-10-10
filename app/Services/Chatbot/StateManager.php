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
            ConversationState::ASKING_DESIGN => $this->fromAskingDesign($intent, $conversation),
            ConversationState::HAS_DESIGN => $this->fromHasDesign($intent),
            ConversationState::NO_DESIGN => $this->fromNoDesign($intent),
            ConversationState::OFFERING_DESIGN_SERVICE => $this->fromOfferingDesignService($intent),
            ConversationState::COLLECTING_DETAILS => $this->fromCollectingDetails($intent),
            ConversationState::ANSWERING_QUESTIONS => $this->fromAnsweringQuestions($intent, $currentState),
            ConversationState::WAITING_BUDGET => $currentState, // Aguarda ação externa
            ConversationState::WAITING_DESIGN_DECISION => $this->fromWaitingDesignDecision($intent),
            default => $currentState,
        };
    }

    private function fromGreeting(CustomerIntent $intent): ConversationState
    {
        return match($intent) {
            CustomerIntent::GREETING => ConversationState::ASKING_DESIGN,
            CustomerIntent::HAS_DESIGN_YES => ConversationState::HAS_DESIGN,
            CustomerIntent::HAS_DESIGN_NO => ConversationState::NO_DESIGN,
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE,
            CustomerIntent::ASKING_PRODUCTS => ConversationState::ANSWERING_QUESTIONS,
            default => ConversationState::ASKING_DESIGN,
        };
    }

    private function fromAskingDesign(CustomerIntent $intent, Conversation $conversation): ConversationState
    {
        return match($intent) {
            CustomerIntent::HAS_DESIGN_YES,
            CustomerIntent::CONFIRMATION_YES => $this->handleHasDesignYes($conversation),
            CustomerIntent::HAS_DESIGN_NO,
            CustomerIntent::CONFIRMATION_NO => ConversationState::NO_DESIGN,
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE,
            CustomerIntent::ASKING_PRODUCTS => ConversationState::ANSWERING_QUESTIONS,
            default => ConversationState::ASKING_DESIGN, // Mantém perguntando
        };
    }

    private function fromHasDesign(CustomerIntent $intent): ConversationState
    {
        return match($intent) {
            CustomerIntent::PROVIDING_DETAILS => ConversationState::COLLECTING_DETAILS,
            CustomerIntent::SENDING_FILE => ConversationState::WAITING_DESIGN_FILE,
            CustomerIntent::CONFIRMATION_YES => ConversationState::WAITING_BUDGET,
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
            default => ConversationState::COLLECTING_DETAILS,
        };
    }

    private function fromNoDesign(CustomerIntent $intent): ConversationState
    {
        return match($intent) {
            CustomerIntent::WANTS_DESIGN_CREATION,
            CustomerIntent::CONFIRMATION_YES => ConversationState::OFFERING_DESIGN_SERVICE,
            CustomerIntent::WILL_PROVIDE_DESIGN => ConversationState::WAITING_DESIGN_FILE,
            CustomerIntent::ASKING_DELIVERY_TIME,
            CustomerIntent::ASKING_PRICE => ConversationState::ANSWERING_QUESTIONS,
            default => ConversationState::OFFERING_DESIGN_SERVICE,
        };
    }

    private function fromOfferingDesignService(CustomerIntent $intent): ConversationState
    {
        return match($intent) {
            CustomerIntent::CONFIRMATION_YES,
            CustomerIntent::WANTS_DESIGN_CREATION => ConversationState::WAITING_DESIGN_DECISION,
            CustomerIntent::CONFIRMATION_NO,
            CustomerIntent::WILL_PROVIDE_DESIGN => ConversationState::WAITING_DESIGN_FILE,
            default => ConversationState::WAITING_DESIGN_DECISION,
        };
    }

    private function fromCollectingDetails(CustomerIntent $intent): ConversationState
    {
        return match($intent) {
            CustomerIntent::PROVIDING_DETAILS => ConversationState::COLLECTING_DETAILS,
            CustomerIntent::CONFIRMATION_YES,
            CustomerIntent::THANKING => ConversationState::WAITING_BUDGET,
            default => ConversationState::COLLECTING_DETAILS,
        };
    }

    private function fromAnsweringQuestions(CustomerIntent $intent, ConversationState $previousState): ConversationState
    {
        return match($intent) {
            CustomerIntent::HAS_DESIGN_YES => ConversationState::HAS_DESIGN,
            CustomerIntent::HAS_DESIGN_NO => ConversationState::NO_DESIGN,
            CustomerIntent::PROVIDING_DETAILS => ConversationState::COLLECTING_DETAILS,
            CustomerIntent::THANKING,
            CustomerIntent::GOODBYE => ConversationState::COMPLETED,
            default => ConversationState::ASKING_DESIGN, // Volta ao fluxo principal
        };
    }

    private function fromWaitingDesignDecision(CustomerIntent $intent): ConversationState
    {
        return match($intent) {
            CustomerIntent::CONFIRMATION_YES => ConversationState::WAITING_BUDGET,
            CustomerIntent::CONFIRMATION_NO => ConversationState::WAITING_DESIGN_FILE,
            default => ConversationState::WAITING_DESIGN_DECISION,
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
            ConversationState::WAITING_BUDGET => 'waiting_budget',
            ConversationState::OFFERING_DESIGN_SERVICE,
            ConversationState::WAITING_DESIGN_DECISION => 'waiting_design',
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
            'waiting_budget' => ConversationState::WAITING_BUDGET,
            'waiting_design' => ConversationState::OFFERING_DESIGN_SERVICE,
            'completed' => ConversationState::COMPLETED,
            'archived' => ConversationState::ARCHIVED,
            default => ConversationState::GREETING,
        };
    }
}
