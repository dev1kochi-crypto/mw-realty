<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A portal (agent/company) support ticket raised from Contact Us. See App\Services\SupportTicketService
 * for the lifecycle; every reply and status change lives in `messages`, oldest first.
 */
class SupportTicket extends Model
{
    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const AWAITING_CLIENT = 'awaiting_client';
    public const RESOLVED = 'resolved';
    public const CLOSED = 'closed';

    /** Status => [label, css tone] — tone maps to the badge classes in both portal and admin views. */
    public const STATUSES = [
        self::OPEN => ['Open', 'info'],
        self::IN_PROGRESS => ['In Progress', 'primary'],
        self::AWAITING_CLIENT => ['Awaiting Your Reply', 'warning'],
        self::RESOLVED => ['Solved', 'success'],
        self::CLOSED => ['Closed', 'secondary'],
    ];

    /** The "what type of issue is this" select box. */
    public const CATEGORIES = [
        'account' => 'Account & Profile',
        'kyc' => 'KYC / Verification',
        'listings' => 'Property Listings',
        'leads' => 'Leads & CRM',
        'plans' => 'Plans & Subscription',
        'payments' => 'Payments & Invoices',
        'agency' => 'Agency & Agents',
        'technical' => 'Technical Issue / Bug',
        'feature' => 'Feature Request',
        'other' => 'Other',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    protected $fillable = [
        'portal_user_id',
        'category',
        'priority',
        'subject',
        'status',
        'last_reply_by',
        'last_reply_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->oldest()->orderBy('id');
    }

    /** Open, in progress or waiting on the client — anything not yet solved/closed. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::OPEN, self::IN_PROGRESS, self::AWAITING_CLIENT]);
    }

    /** Tickets whose last word came from the client and still need an admin response. */
    public function scopeNeedsAdminReply(Builder $query): Builder
    {
        return $query->whereIn('status', [self::OPEN, self::IN_PROGRESS])->where('last_reply_by', 'client');
    }

    /** Open tickets where MW Realty had the last word (or asked for info) — waiting on the client. */
    public function scopeNeedsClientReply(Builder $query): Builder
    {
        return $query->active()->where(fn ($q) => $q->where('status', self::AWAITING_CLIENT)
            ->orWhere('last_reply_by', SupportTicketMessage::ADMIN));
    }

    public function reference(): string
    {
        return 'TKT-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function statusLabel(bool $forAdmin = false): string
    {
        if ($forAdmin && $this->status === self::AWAITING_CLIENT) {
            return 'Awaiting Client';
        }

        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusTone(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst($this->priority);
    }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }

    public function isSolved(): bool
    {
        return in_array($this->status, [self::RESOLVED, self::CLOSED], true);
    }
}
