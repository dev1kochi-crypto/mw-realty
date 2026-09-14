<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class LandingPage extends Model
{
    protected $fillable = [
        'slug',
        'page_type',
        'translations',
        'feature_image',
        'feature_image_alt',
        'custom_html',
        'custom_css',
        'custom_js',
        'text_translations',
        'use_site_header_footer',
        'thank_you_url',
        'metadata',
        'published_at',
        'order_index',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'use_site_header_footer' => 'boolean',
        'translations' => 'array',
        'text_translations' => 'array',
        'metadata' => 'array',
        'published_at' => 'date',
    ];

    public const TYPE_TEMPLATE = 'template';
    public const TYPE_CUSTOM = 'custom';

    public function getTranslation($attribute, $lang = null)
    {
        $lang = $lang ?? app()->getLocale();
        return $this->translations[$lang][$attribute] ?? ($this->translations[config('app.fallback_locale')][$attribute] ?? null);
    }

    public function isCustom(): bool
    {
        return $this->page_type === self::TYPE_CUSTOM;
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Every distinct, non-empty text node found in $html, in document order — what the
     * "Detect Text" button shows the admin to translate, one row per string.
     */
    public static function extractTranslatableStrings(?string $html): array
    {
        if (!$html || trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $textNodes = $xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]');

        $strings = [];
        foreach ($textNodes as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($text !== '' && !in_array($text, $strings, true)) {
                $strings[] = $text;
            }
        }

        return $strings;
    }

    /**
     * Every distinct image `src` found in $html, in document order — what the "Detect Images"
     * table shows so the admin can replace any of them without touching the raw HTML.
     */
    public static function extractImageSources(?string $html): array
    {
        if (!$html || trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $srcs = [];
        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = trim($img->getAttribute('src'));
            if ($src !== '' && !in_array($src, $srcs, true)) {
                $srcs[] = $src;
            }
        }

        return $srcs;
    }

    /** $html with every detected string swapped for its $lang translation (falls back to the original when none is set). */
    public function renderHtmlForLocale(string $lang): string
    {
        $html = $this->custom_html ?? '';
        $map = $this->text_translations[$lang] ?? [];

        if (empty($map)) {
            return $html;
        }

        // Longest-first so a shorter string that happens to be a substring of a longer
        // one (e.g. "Read" inside "Read More") never clobbers the longer match first.
        usort($map, fn ($a, $b) => strlen($b['original'] ?? '') <=> strlen($a['original'] ?? ''));

        return str_replace(array_column($map, 'original'), array_column($map, 'translated'), $html);
    }

    /**
     * RTL languages this app knows about. NOT applied automatically by renderDocument() below — a theme's
     * CSS has to actually be built for RTL (Bootstrap's RTL stylesheet, logical properties, etc.) for
     * dir="rtl" to do anything but flip flexbox/float ordering into a mirrored mess. Kept here so a future
     * admin-level "this page's theme supports RTL" toggle has a ready list to check against.
     */
    public const RTL_LANGUAGE_CODES = ['ar', 'he', 'fa', 'ur'];

    /**
     * Renders custom_html for $textLookupKey as one complete, standalone document — detecting whether
     * the pasted HTML is already a full document (its own <html>/<head>) or a bare fragment, and only
     * adding what's missing (css/js/meta), instead of nesting a second document inside it. Used by both
     * the admin Preview tab and the public show() route, so preview and the live page always match.
     *
     * $textLookupKey is the key into text_translations to substitute from — normally the same as
     * $displayLang, except the Preview tab uses the fixed pseudo-key "preview" for its live,
     * not-yet-saved translation rows while still wanting the real language's lang attribute.
     * Same layout either way — only the text itself changes, never the direction/structure.
     */
    public function renderDocument(string $textLookupKey, string $displayLang): string
    {
        $html = $this->renderHtmlForLocale($textLookupKey);
        $css = $this->custom_css ?? '';
        $js = $this->custom_js ?? '';
        $isFullDocument = stripos($html, '<html') !== false || stripos(ltrim($html), '<!doctype') === 0;
        // The rest of the SEO tags are always additive, so they're safe to just append — only the
        // <title> needs special handling for a full document, since it already has its own.
        $metaTags = $this->metaTagsHtml(includeTitle: !$isFullDocument);

        if ($isFullDocument) {
            $document = $html;
            if ($css !== '') {
                $document = $this->injectBeforeTag($document, '</head>', '<style>' . $css . '</style>');
            }
            if ($js !== '') {
                $document = $this->injectBeforeTag($document, '</body>', '<script>' . $js . '</script>');
            }
            // Meta Title is meant to actually control the page's title — swap the pasted document's own
            // <title> for it (or add one if it somehow has none) rather than leaving the theme's default.
            if (!empty($this->metadata['meta_title'] ?? null)) {
                $document = $this->replaceOrInsertTitle($document, $this->metadata['meta_title']);
            }
            if ($metaTags !== '') {
                $document = $this->injectBeforeTag($document, '</head>', $metaTags);
            }

            return $document;
        }

        return '<!DOCTYPE html><html lang="' . e($displayLang) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . $metaTags
            . '<style>body{font-family:sans-serif;margin:1.5rem;}' . $css . '</style></head><body>' . $html
            . ($js !== '' ? '<script>' . $js . '</script>' : '') . '</body></html>';
    }

    /** SEO tags built from this page's metadata JSON — reused by renderDocument() and the Template-type view. */
    public function metaTagsHtml(bool $includeTitle = true): string
    {
        $meta = $this->metadata ?? [];
        $tags = [];

        if ($includeTitle && !empty($meta['meta_title'])) {
            $tags[] = '<title>' . e($meta['meta_title']) . '</title>';
        }
        if (!empty($meta['meta_description'])) {
            $tags[] = '<meta name="description" content="' . e($meta['meta_description']) . '">';
        }
        if (!empty($meta['meta_keywords'])) {
            $tags[] = '<meta name="keywords" content="' . e($meta['meta_keywords']) . '">';
        }
        if (!empty($meta['canonical_url'])) {
            $tags[] = '<link rel="canonical" href="' . e($meta['canonical_url']) . '">';
        }
        if (!empty($meta['og_title'])) {
            $tags[] = '<meta property="og:title" content="' . e($meta['og_title']) . '">';
        }
        if (!empty($meta['og_description'])) {
            $tags[] = '<meta property="og:description" content="' . e($meta['og_description']) . '">';
        }
        if (!empty($meta['og_image'])) {
            $tags[] = '<meta property="og:image" content="' . e(asset('storage/' . $meta['og_image'])) . '">';
        }
        if (!empty($meta['other_meta_tags'])) {
            // Raw HTML the admin typed themselves (e.g. <meta name="robots" ...>) — trusted the same
            // way custom_html/custom_css/custom_js already are, not user-submitted input.
            $tags[] = $meta['other_meta_tags'];
        }

        return implode("\n", $tags);
    }

    private function replaceOrInsertTitle(string $html, string $title): string
    {
        $titleTag = '<title>' . e($title) . '</title>';

        if (preg_match('/<title\b[^>]*>.*?<\/title>/is', $html)) {
            return preg_replace('/<title\b[^>]*>.*?<\/title>/is', $titleTag, $html, 1);
        }

        return $this->injectBeforeTag($html, '</head>', $titleTag);
    }

    private function injectBeforeTag(string $html, string $tag, string $insert): string
    {
        $pos = stripos($html, $tag);
        if ($pos === false) {
            return $html . $insert;
        }

        return substr($html, 0, $pos) . $insert . substr($html, $pos);
    }
}
