<?php

namespace App\Models\Visitors;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A website visitor who told us who they are — AI chat details form, a contact / enquiry form or
 * a customer account. Everything they do on the site is tracked as VisitorEvents. Viewing a
 * property routes them to that listing's agency / agent as a CRM lead (crmLeads); until then they
 * wait in Super Admin's Website Leads pool, where they can be transferred by hand.
 */
class VisitorLead extends Model
{
    public const SOURCE_CHATBOT = 'chatbot';
    public const SOURCE_CONTACT_FORM = 'contact-form';
    public const SOURCE_LANDING_PAGE = 'landing-page';
    public const SOURCE_PROPERTY_ENQUIRY = 'property-enquiry';
    public const SOURCE_CUSTOMER_ACCOUNT = 'customer-account';

    public const SOURCE_LABELS = [
        self::SOURCE_CHATBOT => 'AI Chat',
        self::SOURCE_CONTACT_FORM => 'Contact Form',
        self::SOURCE_LANDING_PAGE => 'Landing Page',
        self::SOURCE_PROPERTY_ENQUIRY => 'Property Enquiry',
        self::SOURCE_CUSTOMER_ACCOUNT => 'Customer Account',
    ];

    protected $fillable = [
        'name', 'email', 'phone', 'phone_country_code', 'phone_key', 'user_id', 'source',
        'ip_address', 'user_agent', 'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function events()
    {
        return $this->hasMany(VisitorEvent::class);
    }

    public function conversations()
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function browsers()
    {
        return $this->hasMany(VisitorBrowser::class);
    }

    /** CRM leads this person was routed / transferred to (one per agency or agent). */
    public function crmLeads()
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Not routed to any agency / agent yet — Super Admin's pool. A CRM lead with no owner (custom
     * request, listing without an agency) is still Super Admin's to place, so it doesn't count.
     */
    public function scopeInPool($query)
    {
        return $query->whereDoesntHave('crmLeads', fn ($q) => $q->whereNotNull('portal_user_id'));
    }

    public function scopeRouted($query)
    {
        return $query->whereHas('crmLeads', fn ($q) => $q->whereNotNull('portal_user_id'));
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? ucwords(str_replace('-', ' ', (string) $this->source));
    }

    public function formattedPhone(): ?string
    {
        if (!$this->phone) {
            return null;
        }

        return $this->phone_country_code && !str_starts_with($this->phone, '+')
            ? $this->phone_country_code . ' ' . $this->phone
            : $this->phone;
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->email ?: ($this->formattedPhone() ?: 'Visitor #' . $this->id));
    }

    /** The CRM lead channel (Lead::SOURCE_NAMES) a routed / transferred lead from here gets. */
    public function crmPageSource(): string
    {
        return match ($this->source) {
            self::SOURCE_CHATBOT => 'ai-chatbot',
            self::SOURCE_CONTACT_FORM => 'contact page',
            self::SOURCE_LANDING_PAGE => 'landing page',
            self::SOURCE_CUSTOMER_ACCOUNT => 'customer-account',
            default => 'website-visitor',
        };
    }
}
