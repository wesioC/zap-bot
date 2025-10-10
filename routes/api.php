<?php

use App\Http\Controllers\Api\ChatbotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rota simples para testar o chatbot
Route::post('/chat', [ChatbotController::class, 'sendMessage'])->name('api.chat');

// Rotas de gerenciamento de conversas
Route::get('/conversation/{id}/status', [ChatbotController::class, 'getConversationStatus'])->name('api.conversation.status');
Route::get('/conversations/pending', [ChatbotController::class, 'getPendingConversations'])->name('api.conversations.pending');

// Rotas do webhook do WhatsApp
Route::get('/webhook/whatsapp', [ChatbotController::class, 'verifyWebhook'])->name('api.webhook.verify');
Route::post('/webhook/whatsapp', [ChatbotController::class, 'whatsappWebhook'])->name('api.webhook.whatsapp');
