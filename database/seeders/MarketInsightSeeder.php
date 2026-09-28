<?php

namespace Database\Seeders;

use App\Models\CmsKit\MarketInsight;
use App\Models\CmsKit\SectionLabel;
use App\Services\ManagedFiles;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Starter Market Insights posts (English + Arabic) and the /market-insights page header.
 * Safe to re-run: posts are matched by slug, each image is cropped and uploaded only once (as the
 * post's own files — deleting a post also deletes its images), and a page header the admin has
 * already edited is left alone.
 *
 * The figures in these posts are illustrative sample content — review before going live.
 */
class MarketInsightSeeder extends Seeder
{
    public function run(): void
    {
        // The admin's section screen can create an empty row on first visit, so "already exists"
        // isn't enough — only fill it while it still has no content.
        $section = SectionLabel::firstOrNew(['section_key' => 'market-insights']);
        if (empty($section->translations)) {
            $section->fill([
                'translations' => [
                    'en' => [
                        'listing_title' => 'Market Insights',
                        'title' => 'Research & Reports',
                        'description' => 'Data-led reports, price trends and investment analysis on Dubai and the wider UAE property market, from the MW Realty research team.',
                    ],
                    'ar' => [
                        'listing_title' => 'رؤى السوق',
                        'title' => 'الأبحاث والتقارير',
                        'description' => 'تقارير مبنية على البيانات واتجاهات الأسعار وتحليلات الاستثمار لسوق العقارات في دبي والإمارات من فريق أبحاث MW Realty.',
                    ],
                ],
                'status' => true,
            ])->save();
        }

        foreach ($this->posts() as $index => $post) {
            $insight = MarketInsight::firstOrNew(['slug' => $post['slug']]);

            $insight->fill([
                'topic' => $post['topic'],
                'region' => $post['region'],
                'published_at' => $post['published_at'],
                'image_alt' => $post['image_alt'],
                'is_featured' => $post['featured'] ?? false,
                'order_index' => $index + 1,
                'status' => true,
                'translations' => $this->withAuthor($post),
                'stats' => $post['stats'],
                'extra_fields' => [],
            ]);

            // Each slot gets its own crop at the size it's displayed, so none is ever upscaled.
            foreach (self::IMAGE_SIZES as $field => [$width, $height, $folder]) {
                if ($field === 'featured_image' && empty($post['featured'])) {
                    continue;
                }
                if (!$insight->{$field}) {
                    $insight->{$field} = $this->storeCrop($post['image'], $width, $height, $folder, $post['slug']);
                }
            }

            $insight->save();
        }
    }

    /** field => [width, height, folder] — matches the recommended sizes shown in the admin form. */
    private const IMAGE_SIZES = [
        'card_image' => [1200, 750, 'market-insights/cards'],
        'detail_image' => [1920, 1080, 'market-insights/details'],
        'featured_image' => [1600, 1100, 'market-insights/featured'],
    ];

    /** Centre-crops a bundled frontend image to $width x $height and uploads it (Cloudinary when configured). */
    private function storeCrop(string $relativePath, int $width, int $height, string $folder, string $slug): ?string
    {
        $source = public_path($relativePath);
        $image = is_file($source) ? @imagecreatefromstring(file_get_contents($source)) : false;
        if (!$image) {
            return null;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $scale = max($width / $srcW, $height / $srcH);
        $cropW = (int) round($width / $scale);
        $cropH = (int) round($height / $scale);

        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $image, 0, 0, (int) (($srcW - $cropW) / 2), (int) (($srcH - $cropH) / 2), $width, $height, $cropW, $cropH);

        $temp = tempnam(sys_get_temp_dir(), 'insight') . '.jpg';
        imagejpeg($canvas, $temp, 86);
        imagedestroy($image);
        imagedestroy($canvas);

        try {
            $file = new UploadedFile($temp, "{$slug}-{$width}x{$height}.jpg", 'image/jpeg', null, true);

            return app(ManagedFiles::class)->store($file, $folder);
        } finally {
            @unlink($temp);
        }
    }

    /** English author name/role => Arabic, for the per-language author fields. */
    private const AUTHORS_AR = [
        'Fatima Noor' => 'فاطمة نور',
        'Sarah Ahmed' => 'سارة أحمد',
        'Omar Khalid' => 'عمر خالد',
        'Market Research Analyst' => 'محللة أبحاث السوق',
        'Senior Investment Consultant' => 'مستشارة استثمار أولى',
        'Senior Property Consultant' => 'مستشار عقاري أول',
    ];

    /** Adds the author name/role into each language's translations (they're per-language fields). */
    private function withAuthor(array $post): array
    {
        $translations = $post['translations'];
        foreach ($translations as $lang => $values) {
            foreach (['author_name', 'author_role'] as $field) {
                $translations[$lang][$field] = $lang === 'ar'
                    ? (self::AUTHORS_AR[$post[$field]] ?? $post[$field])
                    : $post[$field];
            }
        }

        return $translations;
    }

    private function stat(string $value, string $trend, string $en, string $ar): array
    {
        return ['value' => $value, 'trend' => $trend, 'label' => ['en' => $en, 'ar' => $ar]];
    }


    private function posts(): array
    {
        return [
            [
                'slug' => 'dubai-property-market-report-q3-2026',
                'topic' => 'market-report', 'region' => 'dubai', 'featured' => true,
                'published_at' => '2026-09-22',
                'author_name' => 'Fatima Noor', 'author_role' => 'Market Research Analyst',
                'image' => 'frontend/assets/images/about/why-choose.jpg', 'image_alt' => 'Downtown Dubai skyline with the Burj Khalifa at sunset',
                'stats' => [
                    $this->stat('+11%', 'up', 'Transactions QoQ', 'الصفقات ربع سنوياً'),
                    $this->stat('+3.2%', 'up', 'Apartment prices', 'أسعار الشقق'),
                    $this->stat('+4.1%', 'up', 'Villa prices', 'أسعار الفلل'),
                    $this->stat('52%', 'flat', 'Off-plan share', 'حصة الخارطة'),
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Dubai Property Market Report: Q3 2026',
                        'summary' => 'Transaction volumes, price movements and rental trends that shaped Dubai\'s residential market this quarter — and what to expect next.',
                        'takeaways' => "Transaction volumes rose around 11% quarter on quarter\nVillas continue to outpace apartments on price growth\nRental growth is cooling as new supply is handed over\nWe expect price growth to moderate into 2027",
                        'content' => '<p>Dubai\'s residential market kept its momentum through the third quarter of 2026. Transaction volumes rose quarter on quarter, driven by end-user demand in established communities and continued appetite for well-located off-plan launches.</p>'
                            . '<h2>Sales Activity</h2><p>Off-plan sales accounted for just over half of all deals, with Dubai Hills Estate, Jumeirah Village Circle and Business Bay among the most active communities. Ready-property transactions were led by end-users upgrading from rental.</p>'
                            . '<h2>Prices</h2><p>Average apartment prices rose roughly 3% over the quarter, while villas climbed closer to 4% as limited new villa supply met strong family demand.</p>'
                            . '<h2>Rents</h2><p>Rental growth is cooling as a larger volume of new units is handed over. Most communities recorded single-digit annual increases, and tenants now have more choice at renewal.</p>'
                            . '<h2>Our Outlook</h2><p>We expect price growth to moderate into 2027 as a larger pipeline of completions reaches the market. For buyers, that means more choice and stronger negotiating positions on ready stock; for investors, careful community selection matters more than ever.</p>',
                    ],
                    'ar' => [
                        'title' => 'تقرير سوق العقارات في دبي: الربع الثالث 2026',
                        'summary' => 'أحجام الصفقات وحركة الأسعار واتجاهات الإيجار التي شكلت السوق السكني في دبي هذا الربع، وما يمكن توقعه لاحقاً.',
                        'takeaways' => "ارتفاع أحجام الصفقات بنحو 11% على أساس ربع سنوي\nالفلل تتفوق على الشقق في نمو الأسعار\nتباطؤ نمو الإيجارات مع تسليم وحدات جديدة\nنتوقع اعتدال نمو الأسعار خلال 2027",
                        'content' => '<p>حافظ السوق السكني في دبي على زخمه خلال الربع الثالث من عام 2026، مع ارتفاع أحجام الصفقات بدعم من طلب المستخدمين النهائيين والإقبال على مشاريع الخارطة المميزة.</p>'
                            . '<h2>الأسعار</h2><p>ارتفع متوسط أسعار الشقق بنحو 3% والفلل بنحو 4% خلال الربع.</p>'
                            . '<h2>توقعاتنا</h2><p>نتوقع أن يعتدل نمو الأسعار خلال 2027 مع دخول المزيد من المشاريع المكتملة إلى السوق.</p>',
                    ],
                ],
            ],
            [
                'slug' => 'best-dubai-communities-for-rental-yield-2026',
                'topic' => 'rental-market', 'region' => 'dubai',
                'published_at' => '2026-09-10',
                'author_name' => 'Sarah Ahmed', 'author_role' => 'Senior Investment Consultant',
                'image' => 'frontend/assets/images/home/project-card-2.jpg', 'image_alt' => 'Modern apartment interior overlooking Dubai towers',
                'stats' => [
                    $this->stat('8.4%', 'up', 'Top gross yield (JVC)', 'أعلى عائد إجمالي'),
                    $this->stat('6.5%', 'flat', 'Dubai average', 'متوسط دبي'),
                    $this->stat('1–2 pts', 'down', 'Service-charge drag', 'أثر رسوم الخدمات'),
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Where to Find the Best Rental Yields in Dubai in 2026',
                        'summary' => 'A community-by-community look at gross rental yields across Dubai, and the costs that decide your real, net return.',
                        'takeaways' => "JVC, Silicon Oasis and International City lead on gross yield\nMarina and Business Bay balance yield with capital growth\nService charges can cut net yield by 1–2 percentage points",
                        'content' => '<p>Dubai remains one of the highest-yielding major property markets in the world, but gross yields vary widely between communities.</p>'
                            . '<h2>High-Yield Apartment Communities</h2><p>Jumeirah Village Circle, Dubai Silicon Oasis and International City continue to deliver gross yields in the 7–9% range, thanks to lower entry prices and consistent tenant demand from young professionals.</p>'
                            . '<h2>Balanced Yield and Capital Growth</h2><p>Business Bay, Dubai Marina and Jumeirah Lake Towers typically return 6–7% gross while offering stronger long-term appreciation and very liquid resale markets.</p>'
                            . '<h2>Before You Buy</h2><ul><li>Budget for service charges — they can reduce net yield by 1–2 percentage points.</li><li>Check the handover pipeline nearby; a wave of new supply can soften rents.</li><li>Consider short-term rental permits where the building allows it.</li></ul>',
                    ],
                    'ar' => [
                        'title' => 'أين تجد أفضل عوائد الإيجار في دبي عام 2026',
                        'summary' => 'نظرة على عوائد الإيجار الإجمالية في مجتمعات دبي والتكاليف التي تحدد عائدك الصافي الحقيقي.',
                        'takeaways' => "قرية جميرا الدائرية وواحة السيليكون والمدينة العالمية في الصدارة\nالمارينا والخليج التجاري يوازنان بين العائد ونمو رأس المال\nرسوم الخدمات قد تخفض العائد الصافي",
                        'content' => '<p>تظل دبي من أعلى الأسواق العقارية عائداً في العالم، لكن العوائد تختلف كثيراً بين المجتمعات.</p><h2>قبل الشراء</h2><ul><li>احسب رسوم الخدمات.</li><li>راجع حجم المعروض الجديد في المنطقة.</li></ul>',
                    ],
                ],
            ],
            [
                'slug' => 'off-plan-vs-ready-property-dubai-2026',
                'topic' => 'investment', 'region' => 'dubai',
                'published_at' => '2026-08-28',
                'author_name' => 'Omar Khalid', 'author_role' => 'Senior Property Consultant',
                'image' => 'frontend/assets/images/home/luxury-card-2.jpg', 'image_alt' => 'Open-plan luxury villa living room opening onto a pool terrace',
                'stats' => [
                    $this->stat('10–20%', 'down', 'Off-plan price discount', 'خصم الخارطة'),
                    $this->stat('3–5 yrs', 'flat', 'Typical payment plan', 'مدة خطة السداد'),
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Off-Plan vs Ready: What the Numbers Say in 2026',
                        'summary' => 'Price, payment plans, yield and risk compared — which route makes more sense for buyers and investors this year.',
                        'takeaways' => "Off-plan entry prices are typically 10–20% below ready stock\nReady property earns rent from day one with no delay risk\nThe community matters more than the format",
                        'content' => '<p>Off-plan sales have dominated Dubai transactions for three years running. But is buying before completion still the smarter move?</p>'
                            . '<h2>The Case for Off-Plan</h2><p>Entry prices are typically 10–20% below comparable ready units, and post-handover payment plans spread the cost over several years.</p>'
                            . '<h2>The Case for Ready</h2><p>Ready property generates rental income immediately, carries no construction or delay risk, and — with more supply arriving — is increasingly negotiable.</p>'
                            . '<h2>Our Take</h2><p>Choose off-plan for long-term growth with a trusted developer and an escrow-protected project; choose ready for immediate yield and certainty.</p>',
                    ],
                    'ar' => [
                        'title' => 'على الخارطة أم جاهز: ماذا تقول الأرقام في 2026',
                        'summary' => 'مقارنة بين الأسعار وخطط السداد والعائد والمخاطر لمعرفة الخيار الأنسب هذا العام.',
                        'takeaways' => "أسعار الخارطة أقل بنسبة 10–20% عادة\nالعقار الجاهز يدر دخلاً فورياً\nالمجتمع أهم من نوع العقار",
                        'content' => '<p>هيمنت مبيعات العقارات على الخارطة على صفقات دبي لثلاث سنوات متتالية.</p><h2>رأينا</h2><p>اختر الخارطة للنمو طويل الأمد، والجاهز للعائد الفوري.</p>',
                    ],
                ],
            ],
            [
                'slug' => 'abu-dhabi-real-estate-outlook-2026',
                'topic' => 'area-spotlight', 'region' => 'abu-dhabi',
                'published_at' => '2026-08-14',
                'author_name' => 'Fatima Noor', 'author_role' => 'Market Research Analyst',
                'image' => 'frontend/assets/images/home/realty-card-2.jpg', 'image_alt' => 'Waterfront residential towers',
                'stats' => [
                    $this->stat('+9%', 'up', 'Saadiyat prices YoY', 'أسعار السعديات سنوياً'),
                    $this->stat('+7%', 'up', 'Yas Island prices YoY', 'أسعار ياس سنوياً'),
                    $this->stat('6.8%', 'flat', 'Avg. gross yield', 'متوسط العائد'),
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Abu Dhabi Real Estate Outlook: Growth Beyond the Capital\'s Core',
                        'summary' => 'Saadiyat, Yas and Al Reem are driving some of the region\'s strongest price growth. Here is what is behind it.',
                        'takeaways' => "Expanded freehold zones open more areas to international buyers\nCultural and leisure projects are lifting surrounding values\nLimited new supply supports both prices and rents",
                        'content' => '<p>While Dubai grabs the headlines, Abu Dhabi\'s property market has quietly posted some of the strongest price growth in the region.</p>'
                            . '<h2>Why Abu Dhabi Is Attracting Buyers</h2><ul><li>Expanded freehold zones open more areas to international buyers.</li><li>Major cultural and leisure projects on Saadiyat and Yas are lifting surrounding values.</li><li>Limited new supply relative to demand supports both prices and rents.</li></ul>'
                            . '<h2>What It Means for Investors</h2><p>Entry prices remain below equivalent Dubai waterfront stock, and rental demand from government and energy-sector professionals is stable.</p>',
                    ],
                    'ar' => [
                        'title' => 'توقعات عقارات أبوظبي: نمو يتجاوز قلب العاصمة',
                        'summary' => 'السعديات وياس والريم تقود بعضاً من أقوى معدلات نمو الأسعار في المنطقة. إليك الأسباب.',
                        'takeaways' => "توسيع مناطق التملك الحر\nمشاريع ثقافية وترفيهية ترفع القيم\nمعروض جديد محدود",
                        'content' => '<p>سجل سوق العقارات في أبوظبي بعضاً من أقوى معدلات نمو الأسعار في المنطقة، بقيادة جزر السعديات وياس والريم.</p>',
                    ],
                ],
            ],
            [
                'slug' => 'uae-mortgage-rates-what-buyers-need-to-know',
                'topic' => 'finance-regulation', 'region' => 'uae',
                'published_at' => '2026-07-30',
                'author_name' => 'Omar Khalid', 'author_role' => 'Senior Property Consultant',
                'image' => 'frontend/assets/images/about/trends-interior.jpg', 'image_alt' => 'Villa bedroom opening onto a private pool',
                'stats' => [
                    $this->stat('80%', 'flat', 'Max LTV (first home)', 'أقصى نسبة تمويل'),
                    $this->stat('4%', 'flat', 'DLD transfer fee', 'رسوم دائرة الأراضي'),
                    $this->stat('0.25%', 'flat', 'Mortgage registration', 'تسجيل الرهن'),
                ],
                'translations' => [
                    'en' => [
                        'title' => 'UAE Mortgage Rates in 2026: What Buyers Need to Know',
                        'summary' => 'Loan-to-value limits, fixed vs variable rates and the upfront costs every mortgage buyer in the UAE should budget for.',
                        'takeaways' => "Expat first-time buyers can borrow up to 80% on homes up to AED 5m\nShort fixed terms with a later refinance are popular\nBudget around 7% of the price for upfront costs",
                        'content' => '<p>Financing conditions have a direct impact on affordability. With rates easing from their recent peak, more buyers are returning to mortgage-backed purchases.</p>'
                            . '<h2>Loan-to-Value Limits</h2><p>UAE Central Bank rules cap borrowing for expatriate first-time buyers at 80% of value for properties up to AED 5 million, and lower above that.</p>'
                            . '<h2>Fixed or Variable?</h2><p>Fixed-rate periods of three to five years offer payment certainty; variable rates linked to EIBOR can be cheaper if rates keep falling.</p>'
                            . '<h2>Upfront Costs to Plan For</h2><ul><li>DLD transfer fee: 4% of the purchase price</li><li>Mortgage registration: 0.25% of the loan amount</li><li>Bank arrangement and valuation fees</li></ul>',
                    ],
                    'ar' => [
                        'title' => 'أسعار الرهن العقاري في الإمارات 2026: ما يجب أن يعرفه المشترون',
                        'summary' => 'نسب التمويل والفائدة الثابتة مقابل المتغيرة والتكاليف المسبقة التي يجب أن يخطط لها كل مشترٍ.',
                        'takeaways' => "تمويل حتى 80% للمشترين لأول مرة\nفترات تثبيت قصيرة ثم إعادة التمويل\nخصص نحو 7% من السعر للتكاليف المسبقة",
                        'content' => '<p>مع تراجع أسعار الفائدة عن ذروتها الأخيرة، يعود المزيد من المشترين إلى التمويل العقاري.</p>',
                    ],
                ],
            ],
            [
                'slug' => 'luxury-villa-market-dubai-2026',
                'topic' => 'price-trends', 'region' => 'dubai',
                'published_at' => '2026-07-16',
                'author_name' => 'Sarah Ahmed', 'author_role' => 'Senior Investment Consultant',
                'image' => 'frontend/assets/images/home/find-villa.jpg', 'image_alt' => 'Contemporary luxury villa in Dubai',
                'stats' => [
                    $this->stat('+14%', 'up', 'Prime villa prices YoY', 'أسعار الفلل الفاخرة'),
                    $this->stat('-18%', 'down', 'Prime villa listings', 'المعروض من الفلل'),
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Inside Dubai\'s Luxury Villa Boom',
                        'summary' => 'Why prime villas in Palm Jumeirah, Emirates Hills and Dubai Hills continue to outperform the wider market.',
                        'takeaways' => "Prime villa prices keep outpacing the wider market\nVery little new supply in established prime communities\nMost buyers are purchasing primary residences",
                        'content' => '<p>Prime villas remain the standout performer in Dubai real estate. Palm Jumeirah, Emirates Hills and Dubai Hills Estate have recorded some of the city\'s highest price growth.</p>'
                            . '<h2>Scarcity Drives Value</h2><p>Limited land in established prime communities means very little new villa supply, and resale stock is often snapped up quickly.</p>'
                            . '<h2>Who Is Buying</h2><p>Buyers from Europe, India and the wider GCC dominate, many purchasing as primary residences rather than investments.</p>',
                    ],
                    'ar' => [
                        'title' => 'داخل طفرة الفلل الفاخرة في دبي',
                        'summary' => 'لماذا تواصل الفلل الفاخرة في نخلة جميرا وتلال الإمارات ودبي هيلز التفوق على السوق.',
                        'takeaways' => "أسعار الفلل الفاخرة تتفوق على السوق\nمعروض جديد قليل جداً\nمعظم المشترين يشترون للسكن",
                        'content' => '<p>تبقى الفلل الفاخرة الأفضل أداءً في سوق دبي.</p>',
                    ],
                ],
            ],
        ];
    }
}
