<?php

namespace App\Services;

use App\Models\CmsKit\Ad;
use App\Models\CmsKit\Banner;
use App\Models\CmsKit\Brand;
use App\Models\CmsKit\CommunityHighlight;
use App\Models\CmsKit\FindPropertyItem;
use App\Models\CmsKit\OurBuilderItem;
use App\Models\CmsKit\PopularPlace;
use App\Models\CmsKit\PostPropertyStep;
use App\Models\CmsKit\SectionLabel;
use App\Models\CmsKit\SiteInformation;
use App\Models\CmsKit\Testimonial;
use App\Models\CmsKit\WhyChooseUsItem;
use App\Models\Property;
use App\Support\MapsPropertyCards;
use App\Support\SeoMeta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Builds the entire home page payload in one pass (see HomeController::index).
 *
 * Scale notes (properties/testimonials/etc. tables expected to reach 50k+ rows):
 * - Every query filters on the indexed `status` column first and takes a small, fixed slice
 *   (take(4)/take(8)/...) — never "get everything". See the add_home_query_indexes migration
 *   for the supporting (status, published_at) / (status, price) composite indexes.
 * - The whole result is cached per language for a few minutes (self::CACHE_TTL) — admin
 *   content changes rarely and this is read on every single home page load, so caching turns
 *   ~15 queries into 0 for almost all requests instead of re-running them per visitor. This is
 *   a short-TTL cache (not invalidated on save) rather than tagged/event-based invalidation —
 *   simplest thing that works; a content edit shows up within CACHE_TTL seconds.
 */
class HomePageService
{
    use MapsPropertyCards;

    private const CACHE_TTL = 180; // seconds

    public function getHomeData(string $lang): array
    {
        return Cache::remember("home-page:{$lang}", self::CACHE_TTL, fn () => [
            'banner' => $this->banner($lang),
            'brands' => $this->brands(),
            'ad' => $this->ad('home'),
            'developments' => $this->developments($lang),
            'premiumProperties' => $this->premiumProperties($lang),
            'luxury' => $this->luxury($lang),
            'whyChooseUs' => $this->whyChooseUs($lang),
            'realty' => $this->realty($lang),
            'communities' => $this->communities($lang),
            'findProperties' => $this->findProperties($lang),
            'postProperty' => $this->postProperty($lang),
            'popularPlaces' => $this->popularPlaces($lang),
            'testimonials' => $this->testimonials($lang),
            'contact' => $this->contact($lang),
            'seo' => SeoMeta::forStaticPage('home', $lang),
        ]);
    }

    protected function banner(string $lang): ?array
    {
        $banner = Banner::where('status', true)->orderBy('order_index')->first();
        if (!$banner) {
            return null;
        }

        return [
            'banner_type' => $banner->banner_type,
            'line_1' => $banner->getTranslation('line_1', $lang),
            'line_2' => $banner->getTranslation('line_2', $lang),
            'content' => $banner->getTranslation('content', $lang),
            'image_url' => $banner->image ? media_url($banner->image) : null,
            'image_alt' => $banner->image_alt,
            'video_url' => $banner->video_file ? media_url($banner->video_file) : $banner->video_url,
            'note_text' => data_get($banner->translations, "{$lang}.extra_fields.note_text"),
            'note_badge' => data_get($banner->translations, "{$lang}.extra_fields.note_badge"),
            'note_link_text' => data_get($banner->translations, "{$lang}.extra_fields.note_link_text"),
            'note_link_url' => data_get($banner->translations, "{$lang}.extra_fields.note_link_url"),
        ];
    }

    protected function brands(): array
    {
        return Brand::where('status', true)
            ->orderBy('order_index')
            ->take(30)
            ->get(['id', 'image', 'image_alt'])
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'image_url' => media_url($brand->image),
                'alt' => $brand->image_alt,
            ])->values()->all();
    }

    protected function ad(string $placement): ?array
    {
        $ad = Ad::forPlacement($placement)->currentlyRunning()->orderBy('order_index')->first();
        if (!$ad) {
            return null;
        }

        return [
            'name' => $ad->name,
            'image_url' => media_url($ad->image),
            'mobile_image_url' => $ad->mobile_image ? media_url($ad->mobile_image) : null,
            'image_alt' => $ad->image_alt,
            'link_url' => $ad->link_url,
        ];
    }

    protected function developments(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'home-developments')->where('status', true)->first();
        $cities = $section?->getTranslation('cities', $lang);
        $cities = is_array($cities) ? array_values(array_filter($cities, fn ($c) => trim((string) $c) !== '')) : [];

        $properties = Property::where('status', true)
            ->latest('published_at')
            ->take(8)
            ->get()
            ->map(function ($property) use ($lang, $cities) {
                $mapped = $this->mapProperty($property, $lang);
                $mapped['category'] = $this->matchCityCategory($mapped['location'], $property->getTranslation('address', $lang), $cities);
                return $mapped;
            })
            ->values();

        return [
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'UAE Developments',
            'title' => $section?->getTranslation('title_2', $lang) ?: 'Browse New Projects in the UAE',
            'button_name' => $section?->getTranslation('button_name', $lang) ?: 'View All Properties',
            'button_url' => $section?->getTranslation('button_url', $lang) ?: null,
            'cities' => $cities,
            'properties' => $properties,
        ];
    }

    protected function premiumProperties(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'home-premium-property')->where('status', true)->first();

        return [
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'Exclusive Selection',
            'title' => $section?->getTranslation('title_2', $lang) ?: 'Premium Properties',
            'description' => $section?->getTranslation('description', $lang) ?: 'A highlight of exclusive listings from our premium customers — featured homes selected for exceptional location, quality, and investment value.',
            'button_name' => $section?->getTranslation('button_name', $lang) ?: 'View More Details',
            'button_url' => $section?->getTranslation('button_url', $lang) ?: null,
            'properties' => $this->featuredOrLatestProperties(4)->map(fn ($p) => $this->mapProperty($p, $lang, true))->values(),
        ];
    }

    protected function luxury(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'home-luxury-project')->where('status', true)->first();

        $properties = Property::where('status', true)->orderByDesc('price')->take(4)->get()
            ->map(fn ($p) => array_merge($this->mapProperty($p, $lang), [
                'stat1' => $p->bedrooms ? "{$p->bedrooms} Bed" : '—',
                'stat2' => $p->bathrooms ? "{$p->bathrooms} Bath" : '—',
            ]))->values();

        return [
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'Signature collection',
            'title' => $section?->getTranslation('title_2', $lang) ?: 'Luxury Project',
            'description' => $section?->getTranslation('description', $lang) ?: null,
            'button_text' => $section?->getTranslation('button_name', $lang) ?: 'View More Details',
            'button_url' => $section?->getTranslation('button_url', $lang) ?: null,
            'properties' => $properties,
        ];
    }

    protected function realty(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'home-realty-property')->where('status', true)->first();
        $excludeIds = Property::where('status', true)->orderByDesc('price')->take(4)->pluck('id');

        $properties = Property::where('status', true)
            ->whereNotIn('id', $excludeIds)
            ->latest('published_at')
            ->take(4)
            ->get()
            ->map(fn ($p) => $this->mapProperty($p, $lang, true))
            ->values();

        return [
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'On-market homes',
            'title' => $section?->getTranslation('title_2', $lang) ?: 'Realty Property',
            'description' => $section?->getTranslation('description', $lang) ?: null,
            'button_text' => $section?->getTranslation('button_name', $lang) ?: 'View More Details',
            'button_url' => $section?->getTranslation('button_url', $lang) ?: null,
            'properties' => $properties,
        ];
    }

    protected function whyChooseUs(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'why-choose-us')->where('status', true)->first();
        $items = WhyChooseUsItem::where('status', true)->orderBy('order_index')->take(20)->get();

        return [
            'eyebrow' => $section?->getTranslation('title_2', $lang) ?: 'The MW advantage',
            'title' => $section?->getTranslation('title', $lang) ?: 'Why Choose Us',
            'image_url' => $section?->section_image ? media_url($section->section_image) : null,
            'image_alt' => $section?->section_image_alt,
            'items' => $items->map(fn ($item) => [
                'icon_url' => $item->image ? media_url($item->image) : null,
                'title' => $item->getTranslation('title', $lang),
                'description' => $item->getTranslation('description', $lang),
            ])->values(),
        ];
    }

    protected function communities(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'communities')->where('status', true)->first();
        $items = CommunityHighlight::where('status', true)->orderBy('order_index')->take(40)->get();

        return [
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'Neighbourhood living',
            'title' => $section?->getTranslation('title', $lang) ?: 'Popular Properties in Dubai Communities',
            'items' => $items->map(fn ($item) => [
                'icon_url' => $item->image ? media_url($item->image) : null,
                'name' => $item->getTranslation('title', $lang),
            ])->values(),
        ];
    }

    protected function findProperties(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'find-properties')->where('status', true)->first();
        $items = FindPropertyItem::where('status', true)->orderBy('order_index')->take(12)->get();

        return [
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'Browse by type',
            'title' => $section?->getTranslation('title', $lang) ?: 'Find the Right Property Type',
            'items' => $items->map(fn ($item) => [
                'image_url' => $item->image ? media_url($item->image) : null,
                'title' => $item->getTranslation('title', $lang),
                'count' => $item->propertyCount(),
            ])->values(),
        ];
    }

    protected function postProperty(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'post-property-steps')->where('status', true)->first();
        $steps = PostPropertyStep::where('status', true)->orderBy('order_index')->take(10)->get();

        return [
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'List with MW Realty',
            'title' => $section?->getTranslation('title', $lang) ?: 'Post your property in 3 simple steps',
            'description' => $section?->getTranslation('description', $lang) ?: null,
            'button_text' => $section?->getTranslation('button_text', $lang) ?: 'Start listing',
            'button_url' => $section?->getTranslation('button_url', $lang) ?: null,
            'image_url' => $section?->section_image ? media_url($section->section_image) : null,
            'image_alt' => $section?->section_image_alt,
            'steps' => $steps->values()->map(fn ($step, $i) => [
                'index' => str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                'icon_url' => $step->image ? media_url($step->image) : null,
                'title' => $step->getTranslation('title', $lang),
                'description' => $step->getTranslation('description', $lang),
            ])->values(),
        ];
    }

    protected function popularPlaces(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'popular-places')->where('status', true)->first();
        $places = PopularPlace::where('status', true)->orderBy('order_index')->take(20)->get();

        return [
            'eyebrow' => $section?->getTranslation('title_1', $lang) ?: 'In-demand cities',
            'title' => $section?->getTranslation('title', $lang) ?: 'Most Popular Properties Places',
            'places' => $places->map(fn ($place) => [
                'name' => $place->getTranslation('name', $lang),
                'image_url' => $place->image ? media_url($place->image) : null,
                'image_alt' => $place->image_alt,
            ])->values(),
        ];
    }

    protected function testimonials(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'testimonials')->where('status', true)->first();
        $items = Testimonial::where('status', true)->orderBy('order_index')->take(20)->get();

        return [
            'eyebrow' => $section?->getTranslation('sub_heading_1', $lang) ?: 'From our clients',
            'title' => $section?->getTranslation('title', $lang) ?: 'Why Our Clients Trust Us',
            'description' => $section?->getTranslation('description', $lang) ?: null,
            'items' => $items->map(fn ($item) => [
                'type' => $item->type === 'video' ? 'video' : 'quote',
                'image_url' => $item->image ? media_url($item->image) : null,
                'video_url' => $item->video_file ? media_url($item->video_file) : $item->video_url,
                'name' => $item->getTranslation('name', $lang),
                'role' => $item->getTranslation('designation', $lang),
                'content' => $item->getTranslation('content', $lang),
                'rating' => $item->rating,
            ])->values(),
        ];
    }

    protected function contact(string $lang): array
    {
        $section = SectionLabel::where('section_key', 'contact-us')->where('status', true)->first();
        $info = SiteInformation::first();

        return [
            'eyebrow' => $section?->getTranslation('home_title_1', $lang) ?: "We're here to help",
            'title' => $section?->getTranslation('home_title', $lang) ?: 'Connect with the experts who make property management simple.',
            'description' => $section?->getTranslation('home_description', $lang) ?: 'Experience hassle-free property management, expertly handled by professionals who understand your unique requirements.',
            'address' => $info?->address,
            'phone' => $info?->phone_1,
            'whatsapp_number' => $info?->whatsapp_number,
            'toll_free' => $info?->toll_free,
            'email' => $info?->email_1,
        ];
    }

    /** Everything the standalone /contact page needs, in one request. */
    public function getContactPageData(string $lang): array
    {
        return Cache::remember("contact-page:{$lang}", self::CACHE_TTL, function () use ($lang) {
            $section = SectionLabel::where('section_key', 'contact-us')->where('status', true)->first();
            $info = SiteInformation::first();
            $extra = $section?->extra_fields ?? [];

            return [
                'title' => $section?->getTranslation('title', $lang) ?: 'Get in Touch',
                'description' => $section?->getTranslation('description', $lang) ?: 'Have questions about our properties or services? Our team is here to help you.',
                'map_url' => $extra['map_url'] ?? null,
                'image' => $section?->section_image ? media_url($section->section_image) : null,
                'image_alt' => $section?->section_image_alt,
                'address' => $info?->address,
                'phone' => $info?->phone_1,
                'email' => $info?->email_1,
                'whatsapp_number' => $info?->whatsapp_number,
                'working_hours' => $info?->working_hours,
                'social' => [
                    'facebook' => $info?->facebook,
                    'twitter' => $info?->twitter,
                    'instagram' => $info?->instagram,
                    'linkedin' => $info?->linkedin,
                ],
                'seo' => SeoMeta::forStaticPage('contact', $lang),
            ];
        });
    }

    /** Site-wide footer content (SiteFooter.vue renders once, outside the router, so this is its own endpoint rather than folding into /api/home). */
    public function getFooterData(string $lang): array
    {
        return Cache::remember("footer-data:{$lang}", self::CACHE_TTL, function () use ($lang) {
            $info = SiteInformation::first();

            return [
                'company_name' => $info?->getTranslation('company_name', $lang),
                'address' => $info?->getTranslation('address', $lang),
                'toll_free' => $info?->toll_free,
                'phone' => $info?->phone_1,
                'email' => $info?->email_1,
                'social' => [
                    'facebook' => $info?->facebook,
                    'twitter' => $info?->twitter,
                    'instagram' => $info?->instagram,
                    'linkedin' => $info?->linkedin,
                ],
            ];
        });
    }

    /** Everything the standalone /about page needs, in one request. */
    public function getAboutPageData(string $lang): array
    {
        return Cache::remember("about-page:{$lang}", self::CACHE_TTL, function () use ($lang) {
            $about = SectionLabel::where('section_key', 'about-us')->where('status', true)->first();
            $trends = SectionLabel::where('section_key', 'market-trends')->where('status', true)->first();
            $builders = SectionLabel::where('section_key', 'our-builders')->where('status', true)->first();
            $connect = SectionLabel::where('section_key', 'connect-us')->where('status', true)->first();
            $connectExtra = $connect?->extra_fields ?? [];

            return [
                'about' => [
                    'eyebrow' => $about?->getTranslation('title_2', $lang) ?: 'Overview',
                    'title' => $about?->getTranslation('title_1', $lang) ?: 'About Us',
                    'description' => $about?->getTranslation('description', $lang),
                    'image_url' => $about?->section_image ? media_url($about->section_image) : null,
                    'image_alt' => $about?->section_image_alt,
                ],
                'director' => [
                    'image_url' => $about?->banner ? media_url($about->banner) : null,
                    'image_alt' => $about?->banner_alt,
                    'name' => $about?->getTranslation('ceo_name', $lang),
                    'designation' => $about?->getTranslation('ceo_designation', $lang),
                    'quote' => $about?->getTranslation('ceo_quote', $lang),
                ],
                'postPropertySteps' => PostPropertyStep::where('status', true)->orderBy('order_index')->take(10)->get()
                    ->values()->map(fn ($step, $i) => [
                        'index' => str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                        'icon_url' => $step->image ? media_url($step->image) : null,
                        'title' => $step->getTranslation('title', $lang),
                        'description' => $step->getTranslation('description', $lang),
                    ])->values(),
                'marketTrends' => [
                    'title' => $trends?->getTranslation('title', $lang) ?: 'Real Estate Market Trends & Investment Tips',
                    'image_1_url' => $trends?->section_image ? media_url($trends->section_image) : null,
                    'image_1_alt' => $trends?->section_image_alt,
                    'image_2_url' => $trends?->banner ? media_url($trends->banner) : null,
                    'image_2_alt' => $trends?->banner_alt,
                    'trends' => array_values(array_filter($trends?->getTranslation('trends', $lang) ?? [])),
                ],
                'whyChooseUsItems' => WhyChooseUsItem::where('status', true)->orderBy('order_index')->take(20)->get()
                    ->map(fn ($item) => [
                        'icon_url' => $item->image ? media_url($item->image) : null,
                        'label' => $item->getTranslation('title', $lang),
                    ])->values(),
                'connectUs' => [
                    'title' => $connect?->getTranslation('title', $lang) ?: 'We value and embrace diversity',
                    'text' => $connect?->getTranslation('title_2', $lang),
                    'button_text' => $connect?->getTranslation('button_text', $lang),
                    'button_url' => $connect?->getTranslation('button_url', $lang) ?: '/contact',
                    'video_url' => ($connectExtra['video_source'] ?? null) === 'file' && !empty($connectExtra['video_file'])
                        ? media_url($connectExtra['video_file'])
                        : ($connectExtra['video_url'] ?? null),
                    'poster_url' => $connect?->section_image ? media_url($connect->section_image) : null,
                    'poster_alt' => $connect?->section_image_alt,
                ],
                'builders' => [
                    'title' => $builders?->getTranslation('title', $lang) ?: 'Our Builders',
                    'items' => OurBuilderItem::where('status', true)->orderBy('order_index')->take(20)->get()
                        ->map(fn ($item) => [
                            'image_url' => $item->image ? media_url($item->image) : null,
                            'image_alt' => $item->image_alt,
                        ])->values(),
                ],
                'seo' => SeoMeta::forStaticPage('about', $lang),
            ];
        });
    }

    /** Featured listings first, topped up with the latest others if fewer than $count are featured. */
    protected function featuredOrLatestProperties(int $count)
    {
        $featured = Property::where('status', true)->where('featured', true)->latest('published_at')->take($count)->get();
        if ($featured->count() >= $count) {
            return $featured;
        }

        $filler = Property::where('status', true)
            ->whereNotIn('id', $featured->pluck('id'))
            ->latest('published_at')
            ->take($count - $featured->count())
            ->get();

        return $featured->concat($filler);
    }

    /** Which admin-configured city tab (Developments) a listing's location text matches, if any. */
    protected function matchCityCategory(string $location, ?string $address, array $cities): ?string
    {
        $haystack = Str::lower($location . ' ' . $address);
        foreach ($cities as $cityName) {
            if ($cityName !== '' && Str::contains($haystack, Str::lower($cityName))) {
                return Str::slug($cityName);
            }
        }

        return null;
    }
}
