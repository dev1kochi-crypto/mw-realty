<?php

use App\Services\HomePageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WEB-AR-CONTACT-01: some Arabic CMS texts were saved through a non-UTF-8 connection, so every
 * Arabic letter became "?" (e.g. the Contact page heading "????? ????"). Puts the Arabic back —
 * only on fields that are still all question marks, so anything re-typed in the CMS is kept.
 */
return new class extends Migration
{
    private const TEXTS = [
        'contact-us' => [
            'home_title_1' => 'نحن هنا لمساعدتك',
            'home_title' => 'تواصل مع الخبراء الذين يجعلون إدارة العقارات أمراً بسيطاً.',
            'home_description' => 'استمتع بإدارة عقارات خالية من المتاعب، يتولاها محترفون يفهمون متطلباتك الخاصة.',
            'title' => 'تواصل معنا',
            'description' => 'هل لديك أسئلة حول عقاراتنا أو خدماتنا؟ فريقنا هنا لمساعدتك.',
        ],
        'market-trends' => [
            'title' => 'اتجاهات سوق العقارات ونصائح الاستثمار',
        ],
    ];

    public function up(): void
    {
        foreach (self::TEXTS as $sectionKey => $texts) {
            $row = DB::table('section_labels')->where('section_key', $sectionKey)->first();
            if (!$row) {
                continue;
            }

            $translations = json_decode($row->translations ?? '', true) ?: [];
            $changed = false;
            foreach ($texts as $field => $arabic) {
                $current = $translations['ar'][$field] ?? null;
                if ($current === null || preg_match('/^[\s?.,!:;-]*$/u', $current)) {
                    $translations['ar'][$field] = $arabic;
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('section_labels')->where('id', $row->id)->update([
                    'translations' => json_encode($translations, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }

        HomePageService::flushCache();
    }

    public function down(): void
    {
        // Data repair only — nothing to undo.
    }
};
