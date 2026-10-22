<?php

namespace App\Models\Visitors;

use Illuminate\Database\Eloquent\Model;

/** One AI chat session — the widget's "clear conversation" button starts a new one. */
class ChatConversation extends Model
{
    protected $fillable = ['visitor_lead_id', 'visitor_browser_id', 'message_count', 'last_message_at'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function visitorLead()
    {
        return $this->belongsTo(VisitorLead::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }
}
