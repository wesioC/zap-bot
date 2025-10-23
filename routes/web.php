<?php

use App\Http\Controllers\ConversationsAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ConversationsAdminController::class, 'index'])
    ->name('conversations.index'); 

Route::post('/conversations/{conversation}/status', [ConversationsAdminController::class, 'updateStatus'])
    ->name('conversations.updateStatus');

