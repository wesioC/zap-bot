<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Message as ModelsMessage;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    protected $fillable = [
        'phone',
        'name',
        'has_design',
        'metadata',
    ];

    protected $casts = [
        'has_design' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Relacionamento com conversas
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Pega a conversa ativa ou se houver uma com status completo e a ultima mensagem foi há menos de uma semana, criar uma nova
     */
    public function getOrCreateActiveConversation(): Conversation
    {
        return DB::transaction(function () {
            if ($active = $this->conversations()
                ->where('status', 'active')
                ->latest('updated_at')
                ->first()) {
                return $active;
            }

            $completed = $this->conversations()
                ->where('status', 'completed')
                ->orderByDesc('last_message_at')
                ->orderByDesc('updated_at')
                ->first();

            if ($completed) {
                $lastTs = $completed->last_message_at
                    ?? Message::where('conversation_id', $completed->id)->max('created_at')
                    ?? $completed->updated_at;

                if ($lastTs && \Illuminate\Support\Carbon::parse($lastTs)->gte(now()->subWeek())) {
                    return $completed;
                }
            }

            return $this->conversations()->create([
                'status'        => 'active',
                'state'         => null,
                'intent'        => null,
                'context'       => null,
                'last_message_at' => null,
            ]);
        });
    }


}
