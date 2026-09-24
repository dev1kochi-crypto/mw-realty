<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class PortalUser extends Authenticatable
{
    use Notifiable;

    protected static function booted(): void
    {
        // The public Agent/Agency detail pages are URLed by slug, so every profile needs one —
        // generated from whichever name is actually displayed, unless one was already set
        // explicitly (e.g. by a seeder).
        static::creating(function (PortalUser $portalUser) {
            if ($portalUser->slug) {
                return;
            }

            $base = Str::slug($portalUser->type === 'company' ? ($portalUser->company_name ?: $portalUser->name) : $portalUser->name) ?: 'profile';
            $slug = $base;
            $suffix = 1;
            while (static::where('slug', $slug)->exists()) {
                $slug = $base . '-' . (++$suffix);
            }
            $portalUser->slug = $slug;
        });
    }

    protected $fillable = [
        'type',
        'slug',
        'name',
        'company_name',
        'email',
        'pending_email',
        'phone',
        'whatsapp_number',
        'nationality',
        'avatar',
        'emirates_id_no',
        'emirates_id_document',
        'passport_no',
        'passport_document',
        'passport_expiry',
        'passport_expiry_notified_at',
        'brn_number',
        'company_id',
        'rera_card_document',
        'trade_license_no',
        'trade_license_document',
        'trade_license_expiry',
        'trade_license_expiry_notified_at',
        'orn_number',
        'rera_certificate_document',
        'office_address',
        'trn_number',
        'trn_expiry',
        'trn_expiry_notified_at',
        'authorized_signatory_name',
        'landline',
        'translations',
        'years_of_experience',
        'preferred_areas',
        'features',
        'website',
        'founding_year',
        'badges',
        'metadata',
        'password',
        'otp_code',
        'otp_expires_at',
        'status',
        'status_changed_at',
        'is_active',
        'rejection_reason',
        'document_status',
        'kyc_review_status',
        'kyc_submitted_at',
        'kyc_user_submitted_at',
        'kyc_review_note',
        'plan_id',
        'payment_status',
        'last_payment_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_renews_at',
        'subscription_cancel_at_period_end',
        'scheduled_plan_id',
        'scheduled_interval',
        'billing_interval',
    ];

    /**
     * The five KYC document fields a profile can carry, and the per-document
     * verification status keyed the same way inside document_status.
     */
    public const DOCUMENT_FIELDS = [
        'emirates_id_document', 'passport_document', 'rera_card_document',
        'trade_license_document', 'rera_certificate_document',
    ];

    /** Human-readable label for each of the DOCUMENT_FIELDS above, for admin/email/notification copy. */
    public const DOCUMENT_LABELS = [
        'emirates_id_document' => 'Emirates ID',
        'passport_document' => 'Passport',
        'rera_card_document' => 'RERA Card',
        'trade_license_document' => 'Trade License',
        'rera_certificate_document' => 'RERA Certificate',
    ];

    public static function documentLabel(string $field): string
    {
        return self::DOCUMENT_LABELS[$field] ?? Str::headline($field);
    }

    /**
     * Recognition badges shown on the future public profile — admin-granted only, not something
     * an agent/company can award themselves.
     */
    public const BADGE_OPTIONS = ['MW Broker', 'Quality Lister', 'Responsive Broker'];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    protected $casts = [
        'password' => 'hashed',
        'otp_expires_at' => 'datetime',
        'trade_license_expiry' => 'date',
        'trade_license_expiry_notified_at' => 'date',
        'passport_expiry' => 'date',
        'passport_expiry_notified_at' => 'date',
        'trn_expiry' => 'date',
        'trn_expiry_notified_at' => 'date',
        'last_payment_at' => 'date',
        'subscription_renews_at' => 'datetime',
        'subscription_cancel_at_period_end' => 'boolean',
        'status_changed_at' => 'datetime',
        'kyc_submitted_at' => 'datetime',
        'kyc_user_submitted_at' => 'datetime',
        'is_active' => 'boolean',
        'document_status' => 'array',
        'translations' => 'array',
        'preferred_areas' => 'array',
        'features' => 'array',
        'badges' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Public-profile text (currently just "bio") is stored per-locale here, same JSON-translation
     * pattern as the Plan model, since it's meant for a language-switchable public page.
     */
    public function getTranslation(string $attribute, ?string $lang = null): ?string
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$attribute][$lang]
            ?? $this->translations[$attribute][config('app.fallback_locale')]
            ?? null;
    }

    /**
     * Verification status + optional admin note for one KYC document, defaulting
     * to 'pending' for a document that hasn't been reviewed yet (or not uploaded).
     */
    /** Dummy SEO content generated from the profile's own real fields, used by SeoMeta::resolve()
     *  whenever this profile's own `metadata` doesn't set a given field. */
    public function seoFallback(?string $lang = null): array
    {
        $lang = $lang ?? app()->getLocale();
        $name = $this->type === 'company' ? ($this->company_name ?: $this->name) : $this->name;
        $label = $this->type === 'company' ? 'Agency' : 'Agent';

        return [
            'meta_title' => $name ? "{$name} | MW Realty {$label}" : "MW Realty {$label}",
            'meta_description' => \App\Support\SeoMeta::excerpt($this->getTranslation('bio', $lang))
                ?? "Connect with {$name}, a trusted " . strtolower($label) . ' on MW Realty.',
            'og_image' => $this->avatar ? asset('storage/' . $this->avatar) : null,
        ];
    }

    public function documentStatus(string $field): array
    {
        return ($this->document_status[$field] ?? null) ?: ['status' => 'pending', 'note' => null];
    }

    public function pendingSinceDays(): ?int
    {
        if ($this->status !== 'pending' || !$this->status_changed_at) {
            return null;
        }

        return (int) $this->status_changed_at->diffInDays(now());
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The brokerage this agent is affiliated with (type=company). Every RERA
     * BRN holder must be affiliated with a registered brokerage in Dubai.
     */
    public function company()
    {
        return $this->belongsTo(PortalUser::class, 'company_id');
    }

    public function agents()
    {
        return $this->hasMany(PortalUser::class, 'company_id');
    }

    public function scopeCompanies($query)
    {
        return $query->where('type', 'company');
    }

    // No scopeAgents() here — this model already has an agents() *relationship* (an agency's
    // roster of agents, see below), and a same-named scope would be unreachable via the static
    // PortalUser::agents() call (Eloquent resolves the real method first). Filter with
    // ->where('type', 'agent') directly instead.

    /**
     * How many more properties this owner can list under their current plan,
     * or null if unlimited (no plan assigned = unlimited, matching legacy behavior).
     */
    public function remainingPropertySlots(): ?int
    {
        if (!$this->plan || !$this->plan->status) return 0;
        if ($this->plan->isUnlimited()) {
            return null;
        }

        $used = $this->properties()->count();
        return max(0, $this->plan->property_limit - $used);
    }

    /** Downgrade waiting for the next renewal (see StripeBillingService::scheduleDowngrade()). */
    public function scheduledPlan()
    {
        return $this->belongsTo(Plan::class, 'scheduled_plan_id');
    }

    /** A Stripe subscription is billing this account (active or retrying a failed payment). */
    public function hasStripeSubscription(): bool
    {
        return $this->stripe_subscription_id !== null
            && in_array($this->subscription_status, ['active', 'trialing', 'past_due', 'unpaid'], true);
    }

    /** How many more team agents this company can add under its plan, or null if unlimited. */
    public function remainingAgentSlots(): ?int
    {
        if (!$this->plan || !$this->plan->status) return 0;
        if ($this->plan->agentsUnlimited()) {
            return null;
        }

        return max(0, (int) $this->plan->agent_limit - $this->agents()->count());
    }

    public function hasReportsAccess(): bool
    {
        return (bool) ($this->plan?->status && $this->plan->reports_access);
    }

    public function featurings()
    {
        return $this->hasMany(PropertyFeaturing::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function leadStages()
    {
        return $this->hasMany(LeadStage::class);
    }

    public function leadSources()
    {
        return $this->hasMany(LeadSource::class);
    }

    public function leadTags()
    {
        return $this->hasMany(LeadTag::class);
    }

    public function planPayments()
    {
        return $this->hasMany(PlanPayment::class);
    }

    public function planUpgradeRequests()
    {
        return $this->hasMany(PlanUpgradeRequest::class);
    }

    public function hasPendingPlanUpgradeRequest(): bool
    {
        return $this->planUpgradeRequests()->pending()->exists();
    }

    public function payments()
    {
        return $this->hasMany(PlanPayment::class)->orderByDesc('period_year')->orderByDesc('period_month');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** The name shown wherever this portal user is displayed as an "Owner". */
    public function displayName(): string
    {
        return $this->type === 'company' ? ($this->company_name ?: $this->name) : $this->name;
    }
}
