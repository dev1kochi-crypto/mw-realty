<?php

namespace Database\Seeders;

use App\Models\CmsKit\Metadata;
use Illuminate\Database\Seeder;

/**
 * Keeps the `metadata` table's page_key rows in sync with config('cms-kit.pages.default_pages')
 * — unlike the vendor CmsKit\MetadataSeeder (which uses updateOrCreate and would blank out any
 * admin-entered content on every re-seed), this only ever creates rows for keys that don't exist
 * yet. Existing rows, filled in or not, are never touched.
 *
 * Also removes known-stale generic rows left over from the vendor package's own default seed
 * (careers/media/products/services/blogs — not real page keys in this app), but only when they're
 * still completely blank, so nothing an admin has actually filled in is ever deleted.
 */
class SeoPageMetadataSeeder extends Seeder
{
    private const STALE_KEYS = ['careers', 'media', 'products', 'services', 'blogs'];

    public function run(): void
    {
        Metadata::whereIn('page_key', self::STALE_KEYS)
            ->get()
            ->each(function (Metadata $row) {
                $isBlank = collect(['meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description'])
                    ->every(fn ($field) => empty(array_filter($row->{$field} ?? [])));

                if ($isBlank) {
                    $row->delete();
                }
            });

        foreach (config('cms-kit.pages.default_pages', []) as $page) {
            Metadata::firstOrCreate(
                ['page_key' => $page['key']],
                [
                    'page_name' => ['en' => $page['name']],
                    'canonical_url' => ['en' => ''],
                    'meta_title' => ['en' => ''],
                    'meta_description' => ['en' => ''],
                    'meta_keywords' => ['en' => ''],
                    'og_title' => ['en' => ''],
                    'og_description' => ['en' => ''],
                    'other_meta_tags' => ['en' => ''],
                ],
            );
        }
    }
}
