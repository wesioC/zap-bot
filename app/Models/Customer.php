<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Pega a conversa ativa ou cria uma nova
     */
    public function getOrCreateActiveConversation(): Conversation
    {
        return $this->conversations()
            ->where('status', 'active')
            ->latest()
            ->first() ?? $this->conversations()->create([
                'status' => 'active',
            ]);
    }
}
