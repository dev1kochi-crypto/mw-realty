<?php

namespace App\Models\Visitors;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = ['chat_conversation_id', 'role', 'text', 'properties'];

    protected $casts = [
        'properties' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }
}
