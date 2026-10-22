<?php

namespace App\Services;

use App\Models\CmsKit\Career;
use App\Models\CmsKit\CareerDepartment;
use App\Models\CmsKit\SectionLabel;
use App\Support\SeoMeta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Builds the payloads for the public /careers listing page and /careers/{slug} vacancy page,
 * from the admin's Careers module (Common Section, Vacancies, Departments).
 */
class CareerPageService
{
    private const CACHE_TTL = 180; // seconds

    /** Filters the listing accepts, in the order the page shows them. */
    public const FILTERS = ['department', 'job_type', 'location'];

    public function getListingData(string $lang, array $filters = []): array
    {
        $filters = collect(self::FILTERS)->mapWithKeys(fn ($key) => [$key => trim((string) ($filters[$key] ?? ''))])->all();
        $cacheKey = "careers-listing:{$lang}:" . md5(json_encode($filters));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lang, $filters) {
            $section = SectionLabel::where('section_key', 'careers')->first();
            $departments = $this->departmentLabels($lang);
            $active = Career::active();

            $jobs = (clone $active)->applyFrontendFilters($filters)->ordered()->get();

            return [
                'title' => $section?->getTranslation('title', $lang) ?: 'Careers',
                'description' => $section?->getTranslation('description', $lang),
                'banner_url' => $section?->banner ? media_url($section->banner) : null,
                'banner_alt' => $section?->banner_alt,
                'filters' => $this->filterOptions($active, $lang, $departments),
                'active_filters' => $filters,
                'total_openings' => (clone $active)->count(),
                'jobs' => $jobs->map(fn ($job) => $this->mapCard($job, $lang, $departments))->values(),
                'seo' => SeoMeta::forStaticPage('careers', $lang),
            ];
        });
    }

    public function getJobData(string $lang, string $slug): ?array
    {
        return Cache::remember("career-job:{$lang}:{$slug}", self::CACHE_TTL, function () use ($lang, $slug) {
            $job = Career::active()->where('slug', $slug)->first();
            if (!$job) {
                return null;
            }

            $departments = $this->departmentLabels($lang);
            $related = Career::active()->where('id', '!=', $job->id)
                ->orderByRaw('department = ? desc', [$job->department])
                ->ordered()->take(3)->get();

            return [
                'job' => array_merge($this->mapCard($job, $lang, $departments), [
                    'about' => $job->getTranslation('about', $lang),
                    'responsibilities' => $job->getTranslation('responsibilities', $lang),
                    'requirements' => $job->getTranslation('requirements', $lang),
                    'join_the_team' => $job->getTranslation('join_the_team', $lang),
                    'seo' => SeoMeta::resolve($job->metadata, $this->seoFallback($job, $lang)),
                ]),
                'related' => $related->map(fn ($other) => $this->mapCard($other, $lang, $departments))->values(),
            ];
        });
    }

    /** Dummy SEO content generated from the vacancy's own fields, for when its `metadata` doesn't set one. */
    public function seoFallback(Career $job, ?string $lang = null): array
    {
        $lang = $lang ?? app()->getLocale();
        $title = $job->getTranslation('title', $lang);

        return [
            'meta_title' => $title ? "{$title} | Careers at MW Realty" : 'Careers at MW Realty',
            'meta_description' => SeoMeta::excerpt($job->getTranslation('short_description', $lang) ?: $job->getTranslation('about', $lang)),
        ];
    }

    private function mapCard(Career $job, string $lang, array $departments): array
    {
        return [
            'slug' => $job->slug,
            'title' => $job->getTranslation('title', $lang),
            'short_description' => $job->getTranslation('short_description', $lang),
            'department' => $job->department,
            'department_label' => $departments[$job->department] ?? $job->department,
            'job_type' => $job->job_type,
            'job_type_label' => $this->optionLabel('job_type_options', $job->job_type, $lang),
            'base_label' => $job->base ? $this->optionLabel('base_options', $job->base, $lang) : null,
            'location' => $job->location,
            'country' => $job->country,
            'published_at' => $job->published_date?->format('M d, Y'),
        ];
    }

    /** Only values an active vacancy actually uses — never an option that would filter down to nothing. */
    private function filterOptions($active, string $lang, array $departments): array
    {
        $distinct = fn (string $column) => (clone $active)->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column);

        return [
            'department' => $distinct('department')->map(fn ($value) => ['value' => $value, 'label' => $departments[$value] ?? $value])->values(),
            'job_type' => $distinct('job_type')->map(fn ($value) => ['value' => $value, 'label' => $this->optionLabel('job_type_options', $value, $lang)])->values(),
            'location' => $distinct('location')->map(fn ($value) => ['value' => $value, 'label' => $value])->values(),
        ];
    }

    /** Department English title (what a vacancy stores, see CareerController::getDepartmentOptions) => translated title. */
    private function departmentLabels(string $lang): array
    {
        $fallback = config('app.fallback_locale', 'en');

        return CareerDepartment::where('status', true)->ordered()->get()
            ->mapWithKeys(function ($department) use ($lang, $fallback) {
                $english = trim((string) ($department->translations[$fallback]['title'] ?? ''));

                return $english === '' ? [] : [$english => $department->getTranslation('title', $lang) ?: $english];
            })
            ->all();
    }

    /** Label for a config-defined option key (config/cms/database.php careers.items.{job_type,base}_options). */
    private function optionLabel(string $configKey, string $value, string $lang): string
    {
        $labels = config("cms-kit.database.careers.items.{$configKey}.{$value}");

        if (is_array($labels)) {
            return $labels[$lang] ?? $labels[config('app.fallback_locale', 'en')] ?? reset($labels);
        }

        return is_string($labels) && $labels !== '' ? $labels : Str::headline($value);
    }
}
