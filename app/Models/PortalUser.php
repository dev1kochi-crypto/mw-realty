<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PortalUser extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'type',
        'name',
        'company_name',
        'email',
        'phone',
        'nationality',
        'avatar',
        'emirates_id_no',
        'emirates_id_document',
        'passport_no',
        'passport_document',
        'brn_number',
        'company_id',
        'rera_card_document',
        'trade_license_no',
        'trade_license_document',
        'trade_license_expiry',
        'orn_number',
        'rera_certificate_document',
        'office_address',
        'trn_number',
        'authorized_signatory_name',
        'landline',
        'translations',
        'years_of_experience',
        'preferred_areas',
        'website',
        'founding_year',
        'badges',
        'password',
        'status',
        'status_changed_at',
        'is_active',
        'rejection_reason',
        'document_status',
        'plan_id',
        'payment_status',
        'last_payment_at',
    ];

    /**
     * The five KYC document fields a profile can carry, and the per-document
     * verification status keyed the same way inside document_status.
     */
    public const DOCUMENT_FIELDS = [
        'emirates_id_document', 'passport_document', 'rera_card_document',
        'trade_license_document', 'rera_certificate_document',
    ];

    /**
     * Recognition badges shown on the future public profile — admin-granted only, not something
     * an agent/company can award themselves.
     */
    public const BADGE_OPTIONS = ['MW Broker', 'Quality Lister', 'Responsive Broker'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'trade_license_expiry' => 'date',
        'last_payment_at' => 'date',
        'status_changed_at' => 'datetime',
        'is_active' => 'boolean',
        'document_status' => 'array',
        'translations' => 'array',
        'preferred_areas' => 'array',
        'badges' => 'array',
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
