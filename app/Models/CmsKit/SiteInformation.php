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

    /** `gtag` is one container ID per line (e.g. "GTM-XXXXXXX") — only ever plain IDs, never a
     *  full script, so they're validated against GTM's own ID shape before being used in either
     *  script below rather than trusted as raw HTML like custom_head_script/custom_body_script. */
    private function gtmContainerIds(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->gtag))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => $id !== '' && preg_match('/^GTM-[A-Z0-9]+$/i', $id))
            ->values()
            ->all();
    }

    /** The standard GTM loader snippet, once per configured container — placed as high in <head> as possible. */
    public function gtmHeadScripts(): string
    {
        return collect($this->gtmContainerIds())->map(fn ($id) => <<<HTML
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>
            HTML)->implode("\n");
    }

    /** The standard GTM <noscript> fallback, once per configured container — placed immediately after <body>. */
    public function gtmNoscriptTags(): string
    {
        return collect($this->gtmContainerIds())->map(fn ($id) =>
            '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $id
                . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'
        )->implode("\n");
    }
}


