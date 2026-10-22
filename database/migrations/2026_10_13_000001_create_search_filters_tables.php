<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Website search filters, one set per page (Admin › Filters › Search Filters): Home (fixed filters,
 * labels / values editable), Properties, Commercial, Premium and Off-plan. `filters` / `filter_values`
 * stay the master value lists (CRM › Master › Property Options) the "From the database" options read.
 *
 * Every page starts with what the site shows today — the listing filters, the Location box, Sort,
 * features, amenity shortcuts, Commercial's Properties box and Category values — in every language
 * (Static Translations), so nothing changes until the admin edits them.
 */
return new class extends Migration
{
    private const LISTING_PAGES = ['properties', 'commercial', 'premium', 'offplan'];

    /** The Home banner search's fixed filters (SearchFilter::HOME_FIELDS): field => type. */
    private const HOME_FIELDS = [
        'listing_type' => 'radio',
        'completion_status' => 'radio',
        'property_type' => 'select',
        'bedrooms' => 'select',
        'price' => 'range',
    ];

    /** Static-translation keys of each page's existing headings: section => field => [label, hint]. */
    private const LABEL_KEYS = [
        'properties_listing' => [
            'listing_type' => ['filter_panel.purpose_label', 'filter_bar.purpose_label'],
            'property_type' => ['filter_panel.property_type_label', 'filter_bar.all_type'],
            'bedrooms' => ['filter_panel.bedrooms_title', 'filter_bar.select_bedrooms'],
            'bathrooms' => ['filter_panel.bathrooms_title', 'filter_bar.select_bathrooms'],
            'price' => ['filter_panel.price_title', null],
            'sqft' => ['filter_panel.area_title', null],
        ],
        'commercial' => [
            'property_type' => ['filter_bar.property_type_label', 'filter_bar.all_type'],
            'completion_status' => ['filter_panel.completion_title', null],
            'bathrooms' => ['filter_panel.bathrooms_title', null],
            'price' => ['filter_panel.price_title', null],
            'sqft' => ['filter_panel.area_title', null],
        ],
        'home' => [
            'property_type' => ['hero.search.property_type_label', 'hero.search.property_type_label'],
            'bedrooms' => ['hero.search.bedroom_label', 'hero.search.bedroom_label'],
        ],
    ];

    private array $texts = [];
    private array $langs = ['en'];

    public function up()
    {
        Schema::create('search_filters', function (Blueprint $table) {
            $table->id();
            // Page this filter belongs to: home, properties, commercial, premium, offplan.
            $table->string('page', 30);
            // Property field it searches (App\Support\SearchFilterFields), also the API / URL key.
            $table->string('field', 50);
            // How it is shown: select, radio, checkbox, text, number, range.
            $table->string('type', 20);
            // { "en": { "label": "...", "placeholder": "..." }, ... }
            $table->json('translations')->nullable();
            // Options: "auto" = from the database / built in, "custom" = search_filter_options.
            $table->string('options_source', 10)->default('auto');
            // Number / range settings: min, max, step, compare (min|max|exact).
            $table->json('settings')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['page', 'field']);
        });

        Schema::create('search_filter_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_filter_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->json('translations')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['search_filter_id', 'value']);
        });

        if (!Schema::hasTable('filters')) return;
        if (Schema::hasTable('languages')) {
            $this->langs = DB::table('languages')->where('status', true)->pluck('code')->all() ?: ['en'];
        }

        $this->seedListingFilters();
        $this->seedHomeFilters();
        $this->seedPageControls();
        $this->fillMissingLanguages();

        Cache::forever('search-filters:version', (int) Cache::get('search-filters:version', 1) + 1);
    }

    /** Every listing page starts with the filters the listing page showed before. */
    private function seedListingFilters(): void
    {
        $types = ['listing_type' => 'radio', 'bedrooms' => 'radio', 'bathrooms' => 'radio', 'price' => 'range', 'sqft' => 'range'];
        $settings = ['price' => ['step' => 50000], 'sqft' => ['step' => 50]];
        $current = DB::table('filters')
            ->whereIn('key', ['listing_type', 'completion_status', 'property_type', 'category', 'bedrooms', 'bathrooms', 'price', 'sqft'])
            ->where(fn ($q) => $q->whereNull('show_on')->orWhereJsonContains('show_on', 'listing'))
            ->orderBy('order_index')
            ->get();

        foreach (self::LISTING_PAGES as $page) {
            $order = 1;
            foreach ($current as $filter) {
                // Commercial listings have no bedrooms; the off-plan page is off-plan by definition.
                if ($page === 'commercial' && in_array($filter->key, ['bedrooms', 'category'], true)) continue;
                if ($page === 'offplan' && $filter->key === 'completion_status') continue;

                $this->insertFilter($page, $filter->key, $types[$filter->key] ?? ($filter->type === 'range' ? 'range' : 'select'),
                    $filter->translations, $order++, $settings[$filter->key] ?? null, (bool) $filter->status);
            }
        }
    }

    /** The Home banner search's fixed filters, labelled as before. */
    private function seedHomeFilters(): void
    {
        $labels = DB::table('filters')->whereIn('key', array_keys(self::HOME_FIELDS))->pluck('translations', 'key');
        $order = 1;
        foreach (self::HOME_FIELDS as $field => $type) {
            $this->insertFilter('home', $field, $type,
                $labels[$field] ?? json_encode(['en' => ['label' => ucwords(str_replace('_', ' ', $field))]]),
                $order++, $field === 'price' ? ['step' => 50000] : null);
        }
    }

    /**
     * The rest of each listing page's search: Location box, Sort, features, amenity shortcuts and,
     * on Commercial, the Properties box and the Category values (Buy / Lease / Off-plan).
     */
    private function seedPageControls(): void
    {
        $residential = [
            'start' => [['location', 'text', $this->text('properties_listing.filter_bar.location_label', 'properties_listing.filter_bar.location_placeholder'), []]],
            'end' => [
                ['sort', 'select', $this->text('commercial.filter_panel.sort_title'), []],
                ['features', 'checkbox', $this->text('properties_listing.filter_panel.features_title'), []],
                ['amenities', 'checkbox', $this->text('properties_listing.filter_panel.amenities_title'),
                    ['furnished', 'community-pool', 'parking', 'gym', 'security', 'balcony', 'concierge', 'private-pool']],
            ],
        ];
        $sets = [
            'properties' => $residential,
            'premium' => $residential,
            'offplan' => $residential,
            'commercial' => [
                'start' => [
                    ['location', 'text', $this->text('commercial.filter_bar.location_label', 'commercial.filter_bar.location_placeholder'), []],
                    ['keyword', 'text', $this->text('commercial.filter_bar.properties_label', 'commercial.filter_bar.properties_placeholder'), []],
                ],
                'end' => [
                    ['sort', 'select', $this->text('commercial.filter_panel.sort_title'), ['default', 'recent', 'price_asc', 'price_desc']],
                    ['amenities', 'checkbox', $this->text('commercial.filter_panel.amenities_title'),
                        ['furnished', 'parking', 'pantry', 'meeting-rooms', 'metro', 'security', 'central-ac', 'loading-bay', 'gym', 'concierge']],
                ],
            ],
        ];

        foreach ($sets as $page => $set) {
            $amenityKey = $page === 'commercial' ? 'commercial.filter_panel.amenity_' : 'properties_listing.filter_panel.amenity_';
            $add = function (array $row, int $order) use ($page, $amenityKey) {
                [$field, $type, $translations, $values] = $row;
                $id = $this->insertFilter($page, $field, $type, $translations, $order, null, true, $values ? 'custom' : 'auto');
                foreach (array_values($values) as $i => $value) {
                    // Sort rows stay unlabelled = the built-in wording in every language.
                    $label = $field === 'amenities' ? $this->optionText($amenityKey . str_replace('-', '_', $value)) : null;
                    $this->insertOption($id, $value, $label, $i + 1);
                }
            };

            // Location (and Commercial's Properties box) first, the page controls last.
            DB::table('search_filters')->where('page', $page)->increment('order_index', count($set['start']));
            foreach ($set['start'] as $i => $row) $add($row, $i + 1);
            $order = (int) DB::table('search_filters')->where('page', $page)->max('order_index');
            foreach ($set['end'] as $row) $add($row, ++$order);
        }

        // Commercial's Category = its Purpose filter, offering Buy / Lease / Off-plan (?category=sale|rent|off_plan).
        $category = DB::table('search_filters')->where('page', 'commercial')->where('field', 'listing_type')->first();
        if ($category) {
            DB::table('search_filters')->where('id', $category->id)->update([
                'translations' => $this->text('commercial.filter_bar.category_label', 'commercial.filter_bar.all_categories'),
                'options_source' => 'custom',
            ]);
            $order = 1;
            foreach (['sale' => 'buy', 'rent' => 'lease', 'off_plan' => 'off_plan'] as $value => $key) {
                $this->insertOption($category->id, $value, $this->optionText("commercial.categories.{$key}"), $order++);
            }
        }
    }

    /** Labels copied from the old filters were often English-only: fill other languages from the page's own texts. */
    private function fillMissingLanguages(): void
    {
        foreach (DB::table('search_filters')->get() as $row) {
            $section = match ($row->page) { 'home' => 'home', 'commercial' => 'commercial', default => 'properties_listing' };
            if (!isset(self::LABEL_KEYS[$section][$row->field])) $section = 'properties_listing';
            [$labelKey, $hintKey] = self::LABEL_KEYS[$section][$row->field] ?? [null, null];
            if (!$labelKey) continue;

            $translations = json_decode($row->translations ?? '[]', true) ?: [];
            $changed = false;
            foreach ($this->langs as $code) {
                if (empty($translations[$code]['label']) && ($label = $this->t("{$section}.{$labelKey}", $code))) {
                    $translations[$code]['label'] = $label;
                    $changed = true;
                }
                // Hints only where the English one exists (an empty hint means "use the page's default").
                if ($hintKey && $code !== 'en' && empty($translations[$code]['placeholder']) && !empty($translations['en']['placeholder'])
                    && ($hint = $this->t("{$section}.{$hintKey}", $code))) {
                    $translations[$code]['placeholder'] = $hint;
                    $changed = true;
                }
            }
            if ($changed) {
                DB::table('search_filters')->where('id', $row->id)->update(['translations' => json_encode($translations, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    // ------------------------------------------------------------------------------------------

    private function insertFilter(string $page, string $field, string $type, $translations, int $order, ?array $settings = null, bool $status = true, string $source = 'auto'): int
    {
        $now = now();
        return DB::table('search_filters')->insertGetId([
            'page' => $page, 'field' => $field, 'type' => $type,
            'translations' => is_string($translations) ? $translations : json_encode($translations, JSON_UNESCAPED_UNICODE),
            'options_source' => $source, 'settings' => $settings ? json_encode($settings) : null,
            'order_index' => $order, 'status' => $status, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function insertOption(int $filterId, string $value, ?string $translations, int $order): void
    {
        DB::table('search_filter_options')->insert([
            'search_filter_id' => $filterId, 'value' => $value, 'translations' => $translations,
            'order_index' => $order, 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** {lang: {label, placeholder}} JSON from static-translation keys, in every active language. */
    private function text(?string $label, ?string $placeholder = null): string
    {
        $out = [];
        foreach ($this->langs as $code) {
            $row = array_filter(['label' => $this->t($label, $code), 'placeholder' => $this->t($placeholder, $code)], 'strlen');
            if ($row) $out[$code] = $row;
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    private function optionText(string $key): ?string
    {
        $out = [];
        foreach ($this->langs as $code) {
            if ($label = $this->t($key, $code)) $out[$code] = ['label' => $label];
        }
        return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
    }

    /** A Static Translations text (null when the key or the translations aren't there yet). */
    private function t(?string $key, string $lang): ?string
    {
        if (!$key) return null;
        try {
            $this->texts[$lang] ??= (array) app(\CMS\SiteManager\Services\StaticTranslationService::class)->read($lang);
        } catch (\Throwable) {
            $this->texts[$lang] = [];
        }
        $value = data_get($this->texts[$lang], $key);
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function down()
    {
        Schema::dropIfExists('search_filter_options');
        Schema::dropIfExists('search_filters');
    }
};
