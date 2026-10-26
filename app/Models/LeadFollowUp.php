<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A scheduled follow-up on a lead (status scheduled, with due_at) or an activity already done
 * (status completed, with its outcome). See LeadFollowUpService for every change and reminder.
 */
class LeadFollowUp extends Model
{
    public const TYPES = ['call' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'];

    public const SCHEDULED = 'scheduled';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'lead_id', 'type', 'status', 'due_at', 'note', 'outcome', 'completed_at', 'cancelled_at',
        'reminded_at', 'reminded_to', 'created_by_name', 'completed_by_name',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('lead_follow_ups.status', self::SCHEDULED);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::SCHEDULED && $this->due_at?->isPast();
    }
}
