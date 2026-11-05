<?php

use App\Services\HomePageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Home page SEO copy (SEO audit: generic H1, Lorem Ipsum section text, typos). Same content the
 * admin edits in Home Banner / Section Labels / Popular Places / Find Properties — each field is
 * only replaced while it still holds the old default, Lorem Ipsum or the typo, so anything an
 * admin has already rewritten (here or on live) is left alone. Records are matched by their
 * current text, not by id, since ids differ between servers.
 */
return new class extends Migration
{
    /** Home Banner — line 1 is the hero's <h1>, line 2 the subtitle under it. */
    private const BANNER = [
        'en' => [
            'line_1' => ['Your Trusted Partner in', 'Your Trusted UAE Property Partner'],
            'line_2' => ['Finding the Best Properties', 'Buy, Rent & Invest with MW Realty'],
        ],
        'ar' => [
            'line_1' => ['شريكك الموثوق في', 'شريكك العقاري الموثوق في الإمارات'],
            'line_2' => ['العثور على أفضل العقارات', 'اشترِ واستأجر واستثمر مع إم دبليو ريالتي'],
        ],
    ];

    /** Section label descriptions still on Lorem Ipsum (Arabic placeholder is "لوريم إيبسوم"). */
    private const DESCRIPTIONS = [
        'home-luxury-project' => [
            'en' => 'Hand-picked luxury villas, penthouses and townhouses priced from AED 5 million in Dubai\'s most sought-after communities — each with full photos, property details and a direct line to the listing agent.',
            'ar' => 'تشكيلة مختارة من الفلل والبنتهاوس والتاون هاوس الفاخرة بأسعار تبدأ من 5 ملايين درهم في أرقى مجتمعات دبي، مع صور كاملة وتفاصيل العقار وتواصل مباشر مع الوكيل المسؤول.',
        ],
        'home-realty-property' => [
            'en' => 'Apartments, villas and townhouses for sale and rent across Dubai and the UAE, listed by verified agents and agencies. Compare prices, sizes and locations, then contact the agent directly to arrange a viewing.',
            'ar' => 'شقق وفلل وتاون هاوس للبيع والإيجار في دبي والإمارات، يعرضها وكلاء ووكالات موثوقون. قارن الأسعار والمساحات والمواقع، ثم تواصل مع الوكيل مباشرة لترتيب معاينة.',
        ],
        'post-property-steps' => [
            'en' => 'Owners and agents can list a property on MW Realty in minutes: create an account, add the property details and photos, and publish it to buyers and tenants searching across the UAE.',
            'ar' => 'يمكن للملاك والوكلاء عرض عقاراتهم على إم دبليو ريالتي خلال دقائق: أنشئ حسابًا، وأضف تفاصيل العقار وصوره، ثم انشره للمشترين والمستأجرين الباحثين في جميع أنحاء الإمارات.',
        ],
    ];

    public function up(): void
    {
        $this->updateBanner();
        $this->updateSectionLabels();
        $this->updatePopularPlaces();
        $this->updateFindProperties();

        HomePageService::flushCache();
    }

    private function updateBanner(): void
    {
        foreach (DB::table('banners')->get() as $row) {
            $translations = json_decode($row->translations ?? '', true) ?: [];
            $changed = false;

            foreach (self::BANNER as $lang => $fields) {
                foreach ($fields as $field => [$old, $new]) {
                    if (trim((string) ($translations[$lang][$field] ?? '')) === $old) {
                        $translations[$lang][$field] = $new;
                        $changed = true;
                    }
                }
            }
            if (($translations['en']['extra_fields']['note_badge'] ?? null) === 'Comming Soon') {
                $translations['en']['extra_fields']['note_badge'] = 'Coming Soon';
                $changed = true;
            }

            if ($changed) {
                $this->save('banners', $row->id, $translations);
            }
        }
    }

    private function updateSectionLabels(): void
    {
        foreach (self::DESCRIPTIONS as $sectionKey => $texts) {
            $row = DB::table('section_labels')->where('section_key', $sectionKey)->first();
            if (!$row) {
                continue;
            }

            $translations = json_decode($row->translations ?? '', true) ?: [];
            $changed = false;
            foreach ($texts as $lang => $text) {
                $current = trim(strip_tags((string) ($translations[$lang]['description'] ?? '')));
                if ($current === '' || stripos($current, 'lorem ipsum') !== false || str_contains($current, 'لوريم إيبسوم')) {
                    $translations[$lang]['description'] = $text;
                    $changed = true;
                }
            }

            if ($changed) {
                $this->save('section_labels', $row->id, $translations);
            }
        }

        // Developments: Arabic eyebrow left as "test ar", and the "Sharja" city tab.
        $row = DB::table('section_labels')->where('section_key', 'home-developments')->first();
        if ($row) {
            $translations = json_decode($row->translations ?? '', true) ?: [];
            $changed = false;
            if (($translations['ar']['title_1'] ?? null) === 'test ar') {
                $translations['ar']['title_1'] = 'تصفح المشاريع الجديدة في';
                $changed = true;
            }
            if (is_array($translations['en']['cities'] ?? null)) {
                $fixed = array_map(fn ($city) => $city === 'Sharja' ? 'Sharjah' : $city, $translations['en']['cities']);
                if ($fixed !== $translations['en']['cities']) {
                    $translations['en']['cities'] = $fixed;
                    $changed = true;
                }
            }
            if ($changed) {
                $this->save('section_labels', $row->id, $translations);
            }
        }
    }

    private function updatePopularPlaces(): void
    {
        foreach (DB::table('popular_places')->get() as $row) {
            $translations = json_decode($row->translations ?? '', true) ?: [];
            $name = $translations['en']['name'] ?? null;

            if ($name === 'Sharja') {
                $translations['en']['name'] = 'Sharjah';
                $this->save('popular_places', $row->id, $translations);
            } elseif ($name === 'Sajuk') {
                // Test entry (its Arabic name is Sharjah, which is already listed) — hidden, not
                // deleted, so it can be switched back on in Popular Places if it was intended.
                DB::table('popular_places')->where('id', $row->id)->update(['status' => false, 'updated_at' => now()]);
            }
        }
    }

    private function updateFindProperties(): void
    {
        $seenPenthouse = false;
        foreach (DB::table('find_property_items')->orderBy('order_index')->orderBy('id')->get() as $row) {
            $translations = json_decode($row->translations ?? '', true) ?: [];
            if (($translations['en']['title'] ?? null) !== 'Pent House') {
                continue;
            }

            $update = [];
            if ($row->property_type === 'penthouse' && $seenPenthouse) {
                // The same type twice — the second card becomes Apartments (the most-listed type).
                $translations['en']['title'] = 'Apartment';
                $translations['ar']['title'] = 'شقة';
                $update['property_type'] = 'apartment';
            } else {
                $translations['en']['title'] = 'Penthouse';
                $seenPenthouse = $seenPenthouse || $row->property_type === 'penthouse';
            }

            $this->save('find_property_items', $row->id, $translations, $update);
        }
    }

    private function save(string $table, int $id, array $translations, array $extra = []): void
    {
        DB::table($table)->where('id', $id)->update($extra + [
            'translations' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Content update only — the previous texts were placeholders and typos.
    }
};
