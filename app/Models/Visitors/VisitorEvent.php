<?php

namespace App\Models\Visitors;

use App\Models\Property;
use Illuminate\Database\Eloquent\Model;

/** One thing a visitor did on the website — see VisitorTracker for who records which type. */
class VisitorEvent extends Model
{
    public const PAGE_VIEW = 'page_view';
    public const PROPERTY_VIEW = 'property_view';
    public const SEARCH = 'search';
    public const FAVORITE_ADDED = 'favorite_added';
    public const FAVORITE_REMOVED = 'favorite_removed';
    public const SAVED_SEARCH = 'saved_search';
    public const CHAT_STARTED = 'chat_started';
    public const ENQUIRY = 'enquiry';
    public const CONTACT_CLICK = 'contact_click';
    public const IDENTIFIED = 'identified';
    public const ROUTED = 'routed';
    public const TRANSFERRED = 'transferred';

    /** type => [label, Font Awesome icon]. */
    public const TYPES = [
        self::PAGE_VIEW => ['Viewed page', 'fa-file-lines'],
        self::PROPERTY_VIEW => ['Viewed property', 'fa-house'],
        self::SEARCH => ['Searched listings', 'fa-magnifying-glass'],
        self::FAVORITE_ADDED => ['Added to favorites', 'fa-heart'],
        self::FAVORITE_REMOVED => ['Removed from favorites', 'fa-heart-crack'],
        self::SAVED_SEARCH => ['Saved a search', 'fa-bookmark'],
        self::CHAT_STARTED => ['Chatted with AI assistant', 'fa-robot'],
        self::ENQUIRY => ['Submitted a form', 'fa-envelope-open-text'],
        self::CONTACT_CLICK => ['Clicked to contact', 'fa-hand-pointer'],
        self::IDENTIFIED => ['Identified', 'fa-id-card'],
        self::ROUTED => ['Routed to agency / agent', 'fa-share'],
        self::TRANSFERRED => ['Transferred by Super Admin', 'fa-right-left'],
    ];

    protected $fillable = [
        'visitor_lead_id', 'visitor_browser_id', 'type', 'property_id', 'chat_conversation_id',
        'url', 'title', 'meta', 'duration_seconds',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function visitorLead()
    {
        return $this->belongsTo(VisitorLead::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function label(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function icon(): string
    {
        return self::TYPES[$this->type][1] ?? 'fa-circle';
    }
}
