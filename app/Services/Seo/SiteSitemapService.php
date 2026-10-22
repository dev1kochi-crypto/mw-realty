<?php

namespace App\Services\Seo;

use CMS\SiteManager\Services\SitemapService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * sitemap.xml for this site, built from the database instead of the package's crawler — the
 * public site is a Vue SPA, so a crawler can't discover property / agent / blog links. Bound
 * over CMS\SiteManager\Services\SitemapService (AppServiceProvider), so the CMS "Generate"
 * button, `php artisan sitemap:generate` and the package's jobs all use it. What it lists is
 * configured in config/cms/sitemap.php ('static_pages' + 'sources').
 */
class SiteSitemapService extends SitemapService
{
    /** Always a full rebuild — it's a handful of queries, and it can't drift from the database. */
    public function generate($model = null, bool $isDeletion = false): void
    {
        $this->write($this->pages());
    }

    /**
     * Every public page: url, lastmod, priority, changefreq, plus title / description / section
     * for llms.txt (SiteLlmsTxtService).
     *
     * @return list<array{url: string, lastmod: ?Carbon, priority: float, changefreq: string, title: string, description: string, section: string}>
     */
    public function pages(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $pages = [];

        foreach (config('cms.sitemap.static_pages', []) as $path => [$priority, $changefreq, $title]) {
            $pages[] = [
                'url' => $base . ($path === '/' ? '/' : $path),
                'lastmod' => null,
                'priority' => (float) $priority,
                'changefreq' => $changefreq,
                'title' => $title,
                'description' => '',
                'section' => $path === '/' ? 'Home' : 'Main pages',
            ];
        }

        foreach (config('cms.sitemap.sources', []) as $source) {
            $class = $source['model'] ?? null;
            if (!$class || !class_exists($class)) {
                continue;
            }

            $query = $class::query()->whereNotNull('slug')->where('slug', '!=', '');
            foreach ($source['where'] ?? [] as $column => $value) {
                $query->where($column, $value);
            }

            $query->orderBy('id')->chunkById(200, function ($rows) use (&$pages, $source, $base) {
                foreach ($rows as $row) {
                    $pages[] = [
                        'url' => $base . str_replace('{slug}', rawurlencode($row->slug), $source['path']),
                        'lastmod' => $row->updated_at,
                        'priority' => (float) ($source['priority'] ?? 0.5),
                        'changefreq' => $source['changefreq'] ?? 'weekly',
                        'title' => $this->firstValue($row, $source['title'] ?? ['title', 'name']) ?: Str::headline($row->slug),
                        'description' => Str::limit($this->plain($this->firstValue($row, $source['description'] ?? [])), 180),
                        'section' => $source['section'] ?? 'Pages',
                        'llms_limit' => $source['llms_limit'] ?? null,
                        'llms_more' => isset($source['llms_more']) ? [$base . $source['llms_more'][0], $source['llms_more'][1]] : null,
                    ];
                }
            });
        }

        // One entry per URL (first wins — static pages before a same-path landing page).
        return array_values(collect($pages)->unique('url')->all());
    }

    private function write(array $pages): void
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($pages as $page) {
            $xml->startElement('url');
            $xml->writeElement('loc', $page['url']);
            if ($page['lastmod']) {
                $xml->writeElement('lastmod', $page['lastmod']->toAtomString());
            }
            $xml->writeElement('changefreq', $page['changefreq']);
            $xml->writeElement('priority', number_format($page['priority'], 1));
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        // Write-then-rename so a crawler never reads a half-written file.
        $path = public_path('sitemap.xml');
        file_put_contents($path . '.tmp', $xml->outputMemory());
        rename($path . '.tmp', $path);
    }

    /** First non-empty value: the field translated (getTranslation), else the plain attribute. */
    private function firstValue($row, array $fields): string
    {
        foreach ($fields as $field) {
            $value = method_exists($row, 'getTranslation') ? $row->getTranslation($field) : null;
            if (!is_string($value) || trim($value) === '') {
                $value = $row->getAttribute($field);
            }
            if (is_string($value) && trim($this->plain($value)) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function plain(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES)));
    }
}
