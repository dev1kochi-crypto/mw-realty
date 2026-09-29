<?php

namespace Database\Seeders;

use App\Models\CmsKit\Ad;
use App\Services\ManagedFiles;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Sample ads for the pages with an ad slot (Admin › Ads) that don't have one yet, using the site's
 * own photos — a page that already has an ad (e.g. Home) is never touched.
 * Each ad has an image plus overlay text in English and Arabic. Idempotent — keyed by name,
 * so re-running only adds what's missing; edit or switch them off in Admin › Ads.
 *
 *   php artisan db:seed --class=DemoAdsSeeder
 */
class DemoAdsSeeder extends Seeder
{
    private const ADS = [
        ['Sell Your Property Faster', 'property-details', 'post-property.jpg', '/#post-property',
            ['MW Realty', 'Sell Your Property Faster', 'List with MW Realty and reach thousands of verified buyers across the UAE.'],
            ['إم دبليو ريالتي', 'بِع عقارك بشكل أسرع', 'اعرض عقارك مع إم دبليو ريالتي وتواصل مع آلاف المشترين الموثوقين في الإمارات.']],
        ['Premium Listings — Property Details', 'property-details', 'luxury-card-1.jpg', '/premium-properties',
            ['Premium', 'Discover Premium Homes', 'Hand-picked villas and penthouses across Dubai’s best addresses.'],
            ['مميز', 'اكتشف المنازل المميزة', 'فلل وبنتهاوس مختارة في أرقى عناوين دبي.']],
        ['Off-Plan Launches', 'properties-dubai', 'realty-card-2.jpg', '/properties?category=off_plan',
            ['New Launches', 'Off-Plan Projects in Dubai', 'Flexible payment plans on the city’s newest developments.'],
            ['إطلاقات جديدة', 'مشاريع على الخارطة في دبي', 'خطط سداد مرنة لأحدث المشاريع في المدينة.']],
        ['Luxury on the Palm', 'premium-properties', 'luxury-card-2.jpg', '/premium-properties',
            ['Luxury', 'Waterfront Living on the Palm', 'Private beaches, skyline views and world-class amenities.'],
            ['فخامة', 'حياة على الواجهة المائية في النخلة', 'شواطئ خاصة وإطلالات ساحرة ومرافق عالمية.']],
        ['Realty Picks', 'marketing-properties', 'realty-card-4.jpg', '/marketing-properties',
            ['MW Realty Picks', 'Homes Our Team Recommends', 'Listings hand-selected by MW Realty’s property experts.'],
            ['اختيارات إم دبليو', 'منازل يوصي بها فريقنا', 'عقارات اختارها خبراء إم دبليو ريالتي بعناية.']],
        ['Business Bay Offices', 'commercial', 'realty-card-3.jpg', '/commercial',
            ['Commercial', 'Offices & Retail in Business Bay', 'Grade-A spaces for growing businesses — for sale and lease.'],
            ['تجاري', 'مكاتب ومحلات في الخليج التجاري', 'مساحات من الفئة الأولى للشركات النامية — للبيع والإيجار.']],
        ['Grow With MW Realty — Agents', 'agents', 'find-tall.jpg', '/signup',
            ['For Agents', 'Grow Your Business With MW Realty', 'Get listed, receive qualified leads and manage everything in one CRM.'],
            ['للوسطاء', 'نمِّ أعمالك مع إم دبليو ريالتي', 'اعرض عقاراتك واستقبل عملاء مؤهلين وأدر كل شيء من نظام واحد.']],
        ['Grow With MW Realty — Agent Profile', 'agent-details', 'find-plot.jpg', '/signup',
            ['For Agents', 'Are You an Agent?', 'Join MW Realty and start receiving enquiries today.'],
            ['للوسطاء', 'هل أنت وسيط عقاري؟', 'انضم إلى إم دبليو ريالتي وابدأ باستقبال الاستفسارات اليوم.']],
        ['List Your Agency', 'agencies', 'project-card-5.jpg', '/signup',
            ['For Agencies', 'List Your Agency on MW Realty', 'Bring your whole team, share leads and track performance.'],
            ['للوكالات', 'أدرج وكالتك في إم دبليو ريالتي', 'أضف فريقك بالكامل وشارك العملاء وتابع الأداء.']],
        ['List Your Agency — Agency Profile', 'agency-details', 'luxury-card-3.jpg', '/signup',
            ['For Agencies', 'Run Your Agency Smarter', 'Round-robin leads, agent reports and premium listings in one place.'],
            ['للوكالات', 'أدر وكالتك بذكاء', 'توزيع العملاء وتقارير الوسطاء والإعلانات المميزة في مكان واحد.']],
        ['Market Report — Blogs', 'blogs', 'luxury-card-3.jpg', '/market-insights',
            ['Market Insights', 'Dubai Property Market Report', 'Prices, trends and the areas to watch this year.'],
            ['رؤى السوق', 'تقرير سوق العقارات في دبي', 'الأسعار والاتجاهات والمناطق الواعدة هذا العام.']],
        ['Market Report — Blog Details', 'blog-details', 'find-penthouse-2.jpg', '/market-insights',
            ['Market Insights', 'Where Is the Market Heading?', 'Read our latest analysis of the UAE property market.'],
            ['رؤى السوق', 'إلى أين يتجه السوق؟', 'اقرأ أحدث تحليلاتنا لسوق العقارات في الإمارات.']],
        ['Find Properties — Market Insights', 'market-insights', 'find-penthouse-2.jpg', '/properties',
            ['MW Realty', 'Ready to Make a Move?', 'Browse verified homes for sale and rent across the UAE.'],
            ['إم دبليو ريالتي', 'مستعد للانتقال؟', 'تصفّح منازل موثوقة للبيع والإيجار في جميع أنحاء الإمارات.']],
    ];

    public function run(): void
    {
        $files = app(ManagedFiles::class);
        $order = (int) Ad::max('order_index');
        $created = 0;
        $seededPlacements = [];

        foreach (self::ADS as [$name, $placement, $photo, $link, $en, $ar]) {
            // Never next to (or instead of) an ad the team already runs on that page.
            if (Ad::where('name', $name)->exists() || (!in_array($placement, $seededPlacements, true) && Ad::where('placement', $placement)->exists())) {
                continue;
            }
            $seededPlacements[] = $placement;
            $source = public_path('frontend/assets/images/home/' . $photo);
            if (!is_file($source)) {
                $this->command?->warn("Skipped {$name}: missing {$photo}");
                continue;
            }

            $image = $this->upload($files, $source, $photo);

            Ad::create([
                'name' => $name,
                'placement' => $placement,
                'image' => $image,
                'image_alt' => $en[1],
                'link_url' => $link, // site path — works on any domain
                'translations' => [
                    'en' => ['eyebrow' => $en[0], 'title' => $en[1], 'text' => $en[2]],
                    'ar' => ['eyebrow' => $ar[0], 'title' => $ar[1], 'text' => $ar[2]],
                ],
                'order_index' => ++$order,
                'status' => true,
            ]);
            $created++;
        }

        $this->command?->info("Demo ads created: {$created}.");
    }
    /**
     * Uploads a copy (ManagedFiles moves the file it is given), resized to at most 1600px wide —
     * the source photos are up to 14 MB, far too heavy for an ad.
     */
    public function upload(ManagedFiles $files, string $source, string $name): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ad') . '.jpg';
        [$width, $height] = getimagesize($source);
        if ($width > 1600 && function_exists('imagecreatefromstring')) {
            $src = imagecreatefromstring(file_get_contents($source));
            $out = imagescale($src, 1600, (int) round($height * 1600 / $width), IMG_BICUBIC);
            imagejpeg($out, $tmp, 82);
        } else {
            copy($source, $tmp);
        }

        return $files->store(new UploadedFile($tmp, pathinfo($name, PATHINFO_FILENAME) . '.jpg', 'image/jpeg', null, true), 'ads');
    }
}
