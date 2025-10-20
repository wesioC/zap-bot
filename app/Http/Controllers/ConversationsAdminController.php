<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationsAdminController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::query()
            ->with('customer') // se tiver relação
            ->orderByRaw('COALESCE(last_message_at, updated_at) DESC')
            ->paginate(30);

        return view('conversations.index', compact('conversations'));
    }

    public function updateStatus(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,completed,archived',
        ]);

        $conversation->update([
            'status' => $validated['status'],
        ]);

        return redirect()->route('conversations.index')
            ->with('ok', "Conversa #{$conversation->id} marcada como {$validated['status']}.");
    }
}
