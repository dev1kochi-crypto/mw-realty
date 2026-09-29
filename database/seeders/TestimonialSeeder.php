<?php

namespace Database\Seeders;

use App\Models\CmsKit\SectionLabel;
use App\Models\CmsKit\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Starter "Why Our Clients Trust Us" content (English + Arabic) for the home page: the section
 * heading/eyebrow/description and a mix of video + quote testimonials, using the design's photos
 * and demo video. Safe to re-run: testimonials match by English name, and section text the
 * admin has already written is left alone.
 */
class TestimonialSeeder extends Seeder
{
    private const ASSETS = 'frontend/assets/images/home/';

    public function run(): void
    {
        $this->seedSection();
        $this->moveSeededMediaToCloudinary();

        // Only stored when a new video testimonial is actually created.
        $video = fn () => $this->copyToStorage('frontend/video/testimonial.webm', 'testimonials/videos/demo-testimonial.webm');

        $items = [
            [
                'type' => 'video', 'image' => 'testimonial-max.jpg', 'rating' => 5,
                'en' => ['name' => 'Max Patrick', 'designation' => 'CEO, Patrick Holdings', 'content' => 'MW Realty found us an office in Business Bay within two weeks and handled every step of the lease.'],
                'ar' => ['name' => 'ماكس باتريك', 'designation' => 'الرئيس التنفيذي، باتريك القابضة', 'content' => 'وجدت لنا إم دبليو ريالتي مكتباً في الخليج التجاري خلال أسبوعين وتولّت كل خطوة في عقد الإيجار.'],
            ],
            [
                'type' => 'video', 'image' => 'testimonial-anna.jpg', 'rating' => 5,
                'en' => ['name' => 'Anna James', 'designation' => 'Managing Director, Coastline Interiors', 'content' => 'From the first viewing to the keys, the team made buying our first Dubai home simple.'],
                'ar' => ['name' => 'آنا جيمس', 'designation' => 'المديرة العامة، كوستلاين للتصميم الداخلي', 'content' => 'من أول معاينة حتى استلام المفاتيح، جعل الفريق شراء أول منزل لنا في دبي أمراً سهلاً.'],
            ],
            [
                'type' => 'text', 'image' => 'testimonial-jenifer-avatar.jpg', 'rating' => 5,
                'en' => ['name' => 'Jenifer Jackson', 'designation' => 'Managing Director, JJ Consulting', 'content' => 'We were relocating from London and needed a family villa quickly. Our agent shortlisted homes that matched every requirement, arranged video viewings before we landed, and negotiated a price below what we expected. Honest advice, fast replies and no pressure — exactly what you want when you are moving countries.'],
                'ar' => ['name' => 'جينيفر جاكسون', 'designation' => 'المديرة العامة، جي جي للاستشارات', 'content' => 'كنا ننتقل من لندن ونحتاج إلى فيلا عائلية بسرعة. اختار وكيلنا منازل تطابق كل متطلباتنا، ورتّب معاينات بالفيديو قبل وصولنا، وتفاوض على سعر أقل مما توقعنا. نصيحة صادقة وردود سريعة ودون أي ضغط — تماماً ما تحتاجه عند الانتقال إلى بلد جديد.'],
            ],
            [
                'type' => 'video', 'image' => 'testimonial-johnson.jpg', 'rating' => 5,
                'en' => ['name' => 'Johnson Mathew', 'designation' => 'CEO, Mathew Logistics', 'content' => 'They helped us secure a warehouse in Dubai South with the right power and access for our fleet.'],
                'ar' => ['name' => 'جونسون ماثيو', 'designation' => 'الرئيس التنفيذي، ماثيو للخدمات اللوجستية', 'content' => 'ساعدونا في الحصول على مستودع في دبي الجنوب بالطاقة والمداخل المناسبة لأسطولنا.'],
            ],
            [
                'type' => 'text', 'image' => 'testimonial-anna.jpg', 'rating' => 5,
                'en' => ['name' => 'Priya Nair', 'designation' => 'Investor', 'content' => 'I bought two off-plan apartments through MW Realty. They compared developers, payment plans and expected rental yields side by side, so I could decide with real numbers. Both units were handed over on time and they found tenants for me within a month.'],
                'ar' => ['name' => 'بريا ناير', 'designation' => 'مستثمرة', 'content' => 'اشتريت شقتين على الخارطة عبر إم دبليو ريالتي. قارنوا بين المطورين وخطط الدفع والعوائد الإيجارية المتوقعة جنباً إلى جنب، فاتخذت قراري بأرقام حقيقية. تم تسليم الوحدتين في الموعد ووجدوا لي مستأجرين خلال شهر.'],
            ],
            [
                'type' => 'text', 'image' => 'testimonial-johnson.jpg', 'rating' => 4,
                'en' => ['name' => 'Ahmed Al Mansoori', 'designation' => 'Property Owner, Abu Dhabi', 'content' => 'Listing my villa with MW Realty was the best decision. Professional photos, verified buyers only and regular updates on every enquiry. The sale closed in six weeks at the asking price.'],
                'ar' => ['name' => 'أحمد المنصوري', 'designation' => 'مالك عقار، أبوظبي', 'content' => 'كان عرض فيلتي لدى إم دبليو ريالتي أفضل قرار. صور احترافية ومشترون موثّقون فقط وتحديثات منتظمة عن كل استفسار. تمت عملية البيع خلال ستة أسابيع بالسعر المطلوب.'],
            ],
        ];

        $order = (int) Testimonial::max('order_index');

        foreach ($items as $item) {
            $exists = Testimonial::all()->first(fn (Testimonial $t) => ($t->translations['en']['name'] ?? null) === $item['en']['name']);
            if ($exists) {
                continue;
            }

            $isVideo = $item['type'] === 'video';

            Testimonial::create([
                'type' => $item['type'],
                'image' => $this->copyToStorage(self::ASSETS . $item['image'], 'testimonials/' . $item['image']),
                'image_alt' => $item['en']['name'],
                'video_source' => $isVideo ? 'file' : null,
                'video_file' => $isVideo ? $video() : null,
                'video_url' => null,
                'rating' => $item['rating'],
                'order_index' => ++$order,
                'status' => true,
                'translations' => ['en' => $item['en'], 'ar' => $item['ar']],
                'extra_fields' => [],
            ]);
        }
    }

    /**
     * Keys as saved by Testimonials > Section Settings. Fills blanks only — except the earlier
     * English entry, which had the heading and eyebrow the wrong way round.
     */
    private function seedSection(): void
    {
        $section = SectionLabel::firstOrCreate(['section_key' => 'testimonials']);
        $translations = $section->translations ?? [];

        $defaults = [
            'en' => [
                'section_title' => 'Why Our Clients Trust Us',
                'section_sub_heading_1' => 'From our clients',
                'description' => 'Discover what our customers are saying about their experiences.',
            ],
            'ar' => [
                'section_title' => 'لماذا يثق بنا عملاؤنا',
                'section_sub_heading_1' => 'من عملائنا',
                'description' => 'اكتشف ما يقوله عملاؤنا عن تجاربهم معنا.',
            ],
        ];

        foreach ($defaults as $lang => $values) {
            $current = $translations[$lang] ?? [];

            // Eyebrow text sitting in the heading field (or the same text in both) → reset both.
            $eyebrowInHeading = strcasecmp(trim($current['section_title'] ?? ''), $values['section_sub_heading_1']) === 0
                || (!empty($current['section_title']) && ($current['section_title'] === ($current['section_sub_heading_1'] ?? null)));
            if ($eyebrowInHeading) {
                unset($current['section_title'], $current['section_sub_heading_1']);
            }

            foreach ($values as $key => $value) {
                if (blank($current[$key] ?? null)) {
                    $current[$key] = $value;
                }
            }
            $translations[$lang] = $current;
        }

        $section->update([
            'translations' => $translations,
            'status' => true,
            'extra_fields' => array_merge(['display_home' => true], $section->extra_fields ?? []),
        ]);
    }

    /** Copies a bundled demo file onto the public disk (served at /storage/...), once. */
    /** @var array<string, string> local seed path => stored value (Cloudinary URL or local path) */
    private array $stored = [];

    /**
     * The demo photo / video as the site stores media: uploaded to Cloudinary when it's configured
     * (once per file per run), otherwise copied to the public disk as before.
     */
    private function copyToStorage(string $publicSource, string $target): string
    {
        if (isset($this->stored[$target])) {
            return $this->stored[$target];
        }

        if (\App\Services\CloudinaryMedia::enabled()) {
            // ManagedFiles moves the file it is given, so hand it a temporary copy.
            $tmp = tempnam(sys_get_temp_dir(), 'tst') . '.' . pathinfo($target, PATHINFO_EXTENSION);
            copy(public_path($publicSource), $tmp);
            $directory = trim(dirname($target), '/.');

            return $this->stored[$target] = app(\App\Services\ManagedFiles::class)
                ->store(new \Illuminate\Http\UploadedFile($tmp, basename($target), null, null, true), $directory);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($target)) {
            $disk->put($target, file_get_contents(public_path($publicSource)));
        }

        return $this->stored[$target] = $target;
    }

    /**
     * Testimonials seeded before media moved to Cloudinary still point at local files that don't
     * exist on a server — re-upload those (only the untouched seed paths, never an admin's own upload).
     */
    private function moveSeededMediaToCloudinary(): void
    {
        if (!\App\Services\CloudinaryMedia::enabled()) {
            return;
        }

        foreach (Testimonial::all() as $testimonial) {
            $changes = [];
            if ($testimonial->image && str_starts_with($testimonial->image, 'testimonials/')) {
                $file = basename($testimonial->image);
                if (is_file(public_path(self::ASSETS . $file))) {
                    $changes['image'] = $this->copyToStorage(self::ASSETS . $file, 'testimonials/' . $file);
                }
            }
            if ($testimonial->video_file === 'testimonials/videos/demo-testimonial.webm') {
                $changes['video_file'] = $this->copyToStorage('frontend/video/testimonial.webm', 'testimonials/videos/demo-testimonial.webm');
            }
            if ($changes) {
                $testimonial->update($changes);
                $this->command?->info("Testimonial #{$testimonial->id}: media moved to Cloudinary.");
            }
        }
    }
}
