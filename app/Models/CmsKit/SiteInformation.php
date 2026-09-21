<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class SiteInformation extends Model
{
    protected $table = 'site_information';

    protected $fillable = [
        'company_name', 'address', 'country', 'po_box', 'fax', 'working_hours',
        'phone_1', 'phone_2', 'phone_3', 'phone_4', 'whatsapp_number', 'toll_free',
        'email_1', 'email_2', 'email_3', 'email_4', 'receipt_email',
        'privacy_policy', 'terms_and_conditions', 'disclaimer',
        'logo', 'logo_alt', 'favicon', 'footer_logo', 'footer_logo_alt', 'footer_description',
        'facebook', 'twitter', 'linkedin', 'instagram', 'tiktok', 'snapchat',
        'pinterest', 'youtube', 'skype', 'whatsapp_social', 'vimeo',
        'gtag', 'custom_head_script', 'custom_body_script', 'extra_fields', 'translations'
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'translations' => 'array',
    ];

    /**
     * Where system notifications (new portal registrations, etc.) get sent.
     */
    public static function notificationEmail(): ?string
    {
        $info = static::first();

        return $info?->receipt_email
            ?: $info?->email_1
            ?: config('mail.from.address');
    }

    /**
     * Per-language value for a plain column (privacy_policy, terms_and_conditions, ...), falling
     * back to the fallback locale's translation, then to the plain (default-language) column
     * itself — same pattern as SectionLabel::getTranslation, added here since the admin form
     * already writes translations[lang][attribute] but nothing previously read it back out.
     */
    public function getTranslation(string $attribute, ?string $lang = null): ?string
    {
        $lang = $lang ?? app()->getLocale();
        $fallback = config('app.fallback_locale');

        return $this->translations[$lang][$attribute]
            ?? $this->translations[$fallback][$attribute]
            ?? $this->{$attribute}
            ?? null;
    }

    /** Same as getTranslation(), but for a field nested inside extra_fields (security_settings, cookie_policy). */
    public function getExtraFieldTranslation(string $field, ?string $lang = null): ?string
    {
        $lang = $lang ?? app()->getLocale();
        $fallback = config('app.fallback_locale');

        return $this->translations[$lang]['extra_fields'][$field]
            ?? $this->translations[$fallback]['extra_fields'][$field]
            ?? $this->extra_fields[$field]
            ?? null;
    }
}


