<?php

namespace App\Models;

use App\Models\CmsKit\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in a support ticket's thread — a client/admin reply, or a `system` status-change note. */
class SupportTicketMessage extends Model
{
    public const CLIENT = 'client';
    public const ADMIN = 'admin';
    public const SYSTEM = 'system';

    protected $fillable = [
        'support_ticket_id',
        'author_type',
        'portal_user_id',
        'admin_id',
        'body',
        'attachment_path',
        'attachment_name',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isSystem(): bool
    {
        return $this->author_type === self::SYSTEM;
    }

    /** Admin replies show as "MW Realty Support" to the client; the admin's own name only in the CMS. */
    public function authorName(bool $forAdmin = false): string
    {
        return match ($this->author_type) {
            self::ADMIN => $forAdmin ? ($this->admin?->name ?? 'Admin') : 'MW Realty Support',
            self::CLIENT => $this->portalUser?->displayName() ?? 'Client',
            default => 'System',
        };
    }
}
