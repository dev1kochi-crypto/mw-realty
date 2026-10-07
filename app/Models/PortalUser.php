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
        // Every account starts on the default Free plan unless one was chosen (never "No Plan").
        static::creating(function (PortalUser $portalUser) {
            if (!$portalUser->plan_id) {
                $portalUser->plan_id = Plan::defaultFree()?->id;
            }
        });

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
        'affiliated_brokerage',
        'company_id',
        'rera_card_document',
        'trade_license_no',
        'trade_license_document',
        'trade_license_expiry',
        'trade_license_expiry_notified_at',
        'orn_number',
        'adrec_license_no',
        'adrec_license_expiry',
        'orn_expiry',
        'other_license',
        'other_license_expiry',
        'public_email',
        'secondary_phone',
        'city',
        'position',
        'linkedin_url',
        'spoken_languages',
        'experience_since',
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
        'renewal_reminder_sent_for',
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
        'two_factor_secret',
        'two_factor_recovery_codes',
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
        'renewal_reminder_sent_for' => 'datetime',
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
        'orn_expiry' => 'date',
        'adrec_license_expiry' => 'date',
        'other_license_expiry' => 'date',
        'spoken_languages' => 'array',
        'seen_help_topics' => 'array',
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_prompt_pending' => 'boolean',
        'watermark' => 'array',
        'two_factor_enforced' => 'boolean',
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
            'og_image' => $this->avatar ? media_url($this->avatar) : null,
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

    /** Authenticator-app 2FA is set up and confirmed. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && !empty($this->two_factor_secret);
    }

    /** New account that hasn't yet set up 2FA or chosen "Skip for now" on the sign-up step. */
    public function needsTwoFactorPrompt(): bool
    {
        return $this->two_factor_prompt_pending && !$this->hasTwoFactorEnabled();
    }

    /** 2FA is mandatory for this account: its own company setting, or its agency's. */
    public function twoFactorRequired(): bool
    {
        if ($this->isAgency()) {
            return (bool) $this->two_factor_enforced;
        }

        return $this->isAgencyAgent() && (bool) $this->company?->two_factor_enforced;
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

    /**
     * How many more team agents this company can add under its plan, or null if unlimited.
     * Every relationship the agency itself opened (active, suspended, awaiting approval, invited)
     * holds a slot, so it can't get past its limit by stacking up invitations or pending adds. An
     * agent's unanswered join request doesn't — it's checked when the agency accepts it.
     */
    public function remainingAgentSlots(): ?int
    {
        if (!$this->plan || !$this->plan->status) return 0;
        if ($this->plan->agentsUnlimited()) {
            return null;
        }

        return max(0, (int) $this->plan->agent_limit - $this->usedAgentSlots());
    }

    public function usedAgentSlots(): int
    {
        return $this->agencyMemberships()->open()->where('status', '!=', AgencyAgent::REQUESTED)->count();
    }

    // --- Account types -------------------------------------------------------------------
    // Stored as type (company|agent) + company_id; the three business account types derive from it.

    public const ACCOUNT_AGENCY = 'agency';
    public const ACCOUNT_AGENCY_AGENT = 'agency_agent';
    public const ACCOUNT_INDIVIDUAL_AGENT = 'individual_agent';

    public function isAgency(): bool
    {
        return $this->type === 'company';
    }

    public function isAgent(): bool
    {
        return $this->type === 'agent';
    }

    /** An agent currently working under an agency (company_id is only ever set by an approved membership). */
    public function isAgencyAgent(): bool
    {
        return $this->type === 'agent' && $this->company_id !== null;
    }

    public function isIndependentAgent(): bool
    {
        return $this->type === 'agent' && $this->company_id === null;
    }

    public function accountType(): string
    {
        return match (true) {
            $this->isAgency() => self::ACCOUNT_AGENCY,
            $this->isAgencyAgent() => self::ACCOUNT_AGENCY_AGENT,
            default => self::ACCOUNT_INDIVIDUAL_AGENT,
        };
    }

    /** Every agency relationship this agent has ever had (invitations, requests, stints). */
    public function memberships()
    {
        return $this->hasMany(AgencyAgent::class, 'agent_id');
    }

    /** Every agent relationship this agency has ever had. */
    public function agencyMemberships()
    {
        return $this->hasMany(AgencyAgent::class, 'agency_id');
    }

    /** The agent's membership in its current agency (approved or suspended), if any. */
    public function currentMembership()
    {
        return $this->hasOne(AgencyAgent::class, 'agent_id')->member()->latestOfMany();
    }

    /**
     * Agents that may receive properties/leads for an agency: approved membership, approved +
     * active account, and still pointing at that agency. Ordered by id so round-robin is stable.
     */
    public function eligibleAgentsQuery()
    {
        return PortalUser::query()
            ->where('portal_users.type', 'agent')
            ->where('portal_users.company_id', $this->id)
            ->where('portal_users.status', 'approved')
            ->where('portal_users.is_active', true)
            ->whereExists(fn ($q) => $q->from('agency_agents')
                ->whereColumn('agency_agents.agent_id', 'portal_users.id')
                ->where('agency_agents.agency_id', $this->id)
                ->where('agency_agents.status', AgencyAgent::APPROVED))
            ->orderBy('portal_users.id');
    }

    public function hasEligibleAgent(int $agentId): bool
    {
        return $this->isAgency() && $this->eligibleAgentsQuery()->whereKey($agentId)->exists();
    }

    /**
     * The account a new listing is owned by / counted against: an agency agent lists on behalf of
     * its agency (agency plan limits apply), everyone else lists for themselves.
     */
    public function listingOwner(): PortalUser
    {
        return $this->isAgencyAgent() && $this->company ? $this->company : $this;
    }

    /**
     * Covered by the agency's plan when connected through My Agency / Agents — the agency created the
     * account, invited them, or accepted their join request (then admin approved) — AND that agency has
     * an active plan that includes team agents. Only naming a brokerage in the profile, an admin link, or
     * an old backfilled brokerage link — or an agency without such a plan — leaves them on their own plan.
     */
    public function isOnAgencyPlan(): bool
    {
        if (!$this->isAgencyAgent() || !$this->company) {
            return false;
        }
        // Nothing to be covered by: the agency needs an active plan that includes team agents.
        $agencyPlan = $this->company->plan;
        if (!$agencyPlan?->status || !($agencyPlan->agentsUnlimited() || (int) $agencyPlan->agent_limit > 0)) {
            return false;
        }

        $membership = $this->currentMembership;

        if ($membership === null || $membership->agency_id !== $this->company_id) {
            return false;
        }

        // Connected through My Agency / Agents: the agent's request the agency accepted, or the
        // agency's invitation / new account. Admin links and old backfilled brokerage links don't count.
        return $membership->initiated_by === \App\Services\Agency\AssignmentActor::AGENT
            || ($membership->initiated_by === \App\Services\Agency\AssignmentActor::AGENCY
                && ($membership->account_created_by_agency || $membership->invited_at !== null));
    }

    /** The plan whose entitlements apply: the agency's when covered by it, otherwise the account's own. */
    public function effectivePlan(): ?Plan
    {
        return $this->isOnAgencyPlan() ? $this->company->plan : $this->plan;
    }

    /** Leads assigned to this agent to work (across owners — see Lead::scopeVisibleTo()). */
    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'agent_id');
    }

    public function leadAssignmentSetting()
    {
        return $this->hasOne(AgencyLeadAssignmentSetting::class, 'agency_id');
    }

    public function hasReportsAccess(): bool
    {
        $plan = $this->effectivePlan();

        return (bool) ($plan?->status && $plan->reports_access);
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
