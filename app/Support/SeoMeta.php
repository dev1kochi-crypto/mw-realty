<?php

namespace App\Support;

use App\Models\CmsKit\Metadata;

/**
 * Shared SEO tag resolution + rendering, used by every content type that carries a
 * `metadata` field (Property, Blog, PortalUser) and by static-page Metadata rows —
 * generalizes the same logic LandingPage::metaTagsHtml() already implements for
 * admin-authored pages, so it isn't reimplemented per content type.
 */
class SeoMeta
{
    private const FIELDS = [
        'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
        'og_title', 'og_description', 'og_image', 'other_meta_tags',
    ];

    /**
     * Merges admin-entered metadata over a computed fallback — the explicit value wins
     * whenever it's actually set (non-empty string), otherwise the fallback is used. This
     * is the single place "if there's no metadata, show dummy content instead" happens.
     *
     * @param  array<string, mixed>|null  $explicit
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    public static function resolve(?array $explicit, array $fallback): array
    {
        $explicit = $explicit ?? [];
        $resolved = [];

        foreach (self::FIELDS as $field) {
            $value = $explicit[$field] ?? null;
            $resolved[$field] = (is_string($value) && trim($value) !== '') ? $value : ($fallback[$field] ?? null);
        }

        // og_title/og_description commonly just mirror the main title/description when an
        // admin fills in SEO basics but skips the separate Open Graph fields entirely.
        $resolved['og_title'] = $resolved['og_title'] ?: $resolved['meta_title'];
        $resolved['og_description'] = $resolved['og_description'] ?: $resolved['meta_description'];
        // No page/listing photo set — share previews still get a picture (the home hero, 1920×934).
        // Admins can set a page's own one in SEO › Metadata (Open Graph image).
        $resolved['og_image'] = $resolved['og_image'] ?: asset('frontend/assets/images/home/hero-bg.jpg');

        return $resolved;
    }

    /**
     * Resolves a static page's SEO fields from its admin-editable Metadata row (see
     * config/cms/pages.php for the valid page_key values) plus config/seo-defaults.php as the
     * fallback — the one place this lookup happens, shared by SpaController (server-rendered
     * <head> tags) and the API controllers/services backing that page (client-side sync).
     */
    public static function forStaticPage(string $pageKey, string $lang, ?string $canonicalUrl = null): array
    {
        $row = Metadata::where('page_key', $pageKey)->first();

        $explicit = $row ? [
            'meta_title' => $row->getTranslation('meta_title', $lang),
            'meta_description' => $row->getTranslation('meta_description', $lang),
            'meta_keywords' => $row->getTranslation('meta_keywords', $lang),
            'canonical_url' => $row->getTranslation('canonical_url', $lang),
            'og_title' => $row->getTranslation('og_title', $lang),
            'og_description' => $row->getTranslation('og_description', $lang),
            'og_image' => is_string($row->og_image) && $row->og_image !== '' ? media_url($row->og_image) : null,
            'other_meta_tags' => $row->getTranslation('other_meta_tags', $lang),
        ] : [];

        $fallback = config("seo-defaults.{$pageKey}", []);
        if ($canonicalUrl) {
            $fallback['canonical_url'] = $canonicalUrl;
        }

        return self::resolve($explicit, $fallback);
    }

    /** Renders a resolved array (see resolve()) as <title>/<meta>/<link> tags for <head>. */
    public static function tagsHtml(array $resolved): string
    {
        $tags = [];

        if (!empty($resolved['meta_title'])) {
            $tags[] = '<title>' . e($resolved['meta_title']) . '</title>';
        }
        if (!empty($resolved['meta_description'])) {
            $tags[] = '<meta name="description" content="' . e($resolved['meta_description']) . '">';
        }
        if (!empty($resolved['meta_keywords'])) {
            $tags[] = '<meta name="keywords" content="' . e($resolved['meta_keywords']) . '">';
        }
        if (!empty($resolved['canonical_url'])) {
            $tags[] = '<link rel="canonical" href="' . e($resolved['canonical_url']) . '">';
        }
        if (!empty($resolved['og_title'])) {
            $tags[] = '<meta property="og:title" content="' . e($resolved['og_title']) . '">';
        }
        if (!empty($resolved['og_description'])) {
            $tags[] = '<meta property="og:description" content="' . e($resolved['og_description']) . '">';
        }
        if (!empty($resolved['og_image'])) {
            $tags[] = '<meta property="og:image" content="' . e($resolved['og_image']) . '">';
        }
        $tags[] = '<meta property="og:type" content="website">';
        $tags[] = '<meta property="og:site_name" content="' . e(config('app.name', 'MW Realty')) . '">';
        if (!empty($resolved['canonical_url'])) {
            $tags[] = '<meta property="og:url" content="' . e($resolved['canonical_url']) . '">';
        }
        // Twitter/X reuses the same title, description and image.
        $tags[] = '<meta name="twitter:card" content="' . (!empty($resolved['og_image']) ? 'summary_large_image' : 'summary') . '">';
        if (!empty($resolved['og_title'])) {
            $tags[] = '<meta name="twitter:title" content="' . e($resolved['og_title']) . '">';
        }
        if (!empty($resolved['og_description'])) {
            $tags[] = '<meta name="twitter:description" content="' . e($resolved['og_description']) . '">';
        }
        if (!empty($resolved['og_image'])) {
            $tags[] = '<meta name="twitter:image" content="' . e($resolved['og_image']) . '">';
        }
        if (!empty($resolved['other_meta_tags'])) {
            // Admin-typed raw HTML (e.g. <meta name="robots" ...>) — trusted the same way
            // LandingPage's custom_html/custom_css/custom_js already are, not user input.
            $tags[] = $resolved['other_meta_tags'];
        }

        return implode("\n", $tags);
    }

    /**
     * schema.org RealEstateAgent JSON-LD for the home page, from Site Information (name, logo,
     * address, phones, email, hours, social links) — so search engines and AI assistants can
     * identify the business. Returns a ready <script> tag, or null with no Site Information row.
     */
    public static function businessSchema(?\App\Models\CmsKit\SiteInformation $info, array $resolved): ?string
    {
        if (!$info) {
            return null;
        }

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateAgent',
            'name' => $info->company_name ?: config('app.name', 'MW Realty'),
            'url' => rtrim(url('/'), '/') . '/',
            'description' => $resolved['meta_description'] ?? null,
            'logo' => $info->logo ? media_url($info->logo) : null,
            'image' => $resolved['og_image'] ?? null,
            'telephone' => $info->phone_1,
            'email' => $info->email_1,
            'openingHours' => $info->working_hours,
            'address' => $info->address ? [
                '@type' => 'PostalAddress',
                'streetAddress' => $info->address,
                'addressLocality' => 'Dubai',
                'addressRegion' => 'Dubai',
                'addressCountry' => 'AE',
            ] : null,
            'areaServed' => [
                ['@type' => 'Country', 'name' => 'United Arab Emirates'],
                ['@type' => 'City', 'name' => 'Dubai'],
            ],
            'contactPoint' => $info->phone_1 || $info->email_1 ? array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'telephone' => $info->phone_1,
                'email' => $info->email_1,
                'areaServed' => 'AE',
                'availableLanguage' => ['English', 'Arabic'],
            ]) : null,
            'sameAs' => array_values(array_filter([
                $info->facebook, $info->instagram, $info->linkedin, $info->twitter,
                $info->youtube, $info->tiktok, $info->pinterest, $info->snapchat,
            ], fn ($link) => is_string($link) && str_starts_with($link, 'http'))) ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        return '<script type="application/ld+json">'
            . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
            . '</script>';
    }

    /** Truncates plain text to an SEO-friendly meta description length without cutting mid-word. */
    public static function excerpt(?string $text, int $length = 160): ?string
    {
        $text = trim(strip_tags((string) $text));
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, mb_strrpos(mb_substr($text, 0, $length), ' ') ?: $length) . '…';
    }
}
