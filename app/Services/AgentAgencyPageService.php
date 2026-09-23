<?php

namespace App\Services;

use App\Models\CmsKit\SectionLabel;
use App\Models\PortalUser;
use App\Models\Property;
use App\Support\MapsPropertyCards;
use App\Support\SeoMeta;
use Illuminate\Support\Facades\Cache;

/** Builds the payloads for the /agents, /agent-details/{slug}, /agencies and /agency-details/{slug} pages. */
class AgentAgencyPageService
{
    use MapsPropertyCards;

    private const CACHE_TTL = 180; // seconds

    public function getAgentsListing(string $lang): array
    {
        return Cache::remember("agents-listing:{$lang}", self::CACHE_TTL, function () use ($lang) {
            $section = SectionLabel::where('section_key', 'agents')->where('status', true)->first();
            $agents = PortalUser::where('type', 'agent')->approved()->where('is_active', true)
                ->orderBy('name')
                ->get();

            return [
                'title' => $section?->getTranslation('title_1', $lang) ?: 'Our Agent',
                'agents' => $agents->map(fn ($agent) => $this->mapAgentCard($agent))->values(),
                'seo' => SeoMeta::forStaticPage('agents', $lang),
            ];
        });
    }

    public function getAgentDetail(string $lang, string $slug): ?array
    {
        return Cache::remember("agent-detail:{$lang}:{$slug}", self::CACHE_TTL, function () use ($lang, $slug) {
            $agent = PortalUser::where('type', 'agent')->approved()->where('slug', $slug)->first();
            if (!$agent) {
                return null;
            }

            $activeProperties = Property::where('status', true)->where('agent_id', $agent->id);

            return [
                'slug' => $agent->slug,
                'name' => $agent->name,
                'avatar_url' => $agent->avatar ? asset('storage/'.$agent->avatar) : null,
                'years_of_experience' => $agent->years_of_experience,
                'preferred_areas' => $agent->preferred_areas ?? [],
                'badges' => $agent->badges ?? [],
                'bio' => $agent->getTranslation('bio', $lang),
                'email' => $agent->email,
                'phone' => $agent->phone,
                'whatsapp_number' => $agent->whatsapp_number,
                'company' => $agent->company ? [
                    'slug' => $agent->company->slug,
                    'name' => $agent->company->displayName(),
                ] : null,
                'rent_count' => (clone $activeProperties)->where('listing_type', 'rent')->count(),
                'sell_count' => (clone $activeProperties)->where('listing_type', 'sale')->count(),
                'properties' => (clone $activeProperties)->orderByDesc('published_at')->take(6)->get()
                    ->map(fn ($p) => $this->mapProperty($p, $lang, true))->values(),
                'seo' => SeoMeta::resolve($agent->metadata, $agent->seoFallback($lang)),
            ];
        });
    }

    public function getAgenciesListing(string $lang): array
    {
        return Cache::remember("agencies-listing:{$lang}", self::CACHE_TTL, function () use ($lang) {
            $section = SectionLabel::where('section_key', 'agencies')->where('status', true)->first();
            $agencies = PortalUser::companies()->approved()->where('is_active', true)
                ->withCount('properties')
                ->orderBy('company_name')
                ->get();

            return [
                'title' => $section?->getTranslation('title_1', $lang) ?: 'Our Agencies',
                'section_title' => $section?->getTranslation('title_2', $lang) ?: 'Top Agencies',
                'section_description' => $section?->getTranslation('description', $lang) ?: 'Explore agency with a proven track record of high response rates and authentic listings.',
                'agencies' => $agencies->map(fn ($agency) => $this->mapAgencyCard($agency))->values(),
                'seo' => SeoMeta::forStaticPage('agencies', $lang),
            ];
        });
    }

    public function getAgencyDetail(string $lang, string $slug): ?array
    {
        return Cache::remember("agency-detail:{$lang}:{$slug}", self::CACHE_TTL, function () use ($lang, $slug) {
            $agency = PortalUser::companies()->approved()->where('slug', $slug)->first();
            if (!$agency) {
                return null;
            }

            $activeProperties = Property::where('status', true)->where('portal_user_id', $agency->id);
            $activeAgents = $agency->agents()->approved()->where('is_active', true);

            return [
                'slug' => $agency->slug,
                'name' => $agency->displayName(),
                'logo_url' => $agency->avatar ? asset('storage/'.$agency->avatar) : null,
                'founding_year' => $agency->founding_year,
                'office_address' => $agency->office_address,
                'website' => $agency->website,
                'orn_number' => $agency->orn_number,
                'email' => $agency->email,
                'phone' => $agency->phone,
                'whatsapp_number' => $agency->whatsapp_number,
                'bio' => $agency->getTranslation('bio', $lang),
                'agent_count' => (clone $activeAgents)->count(),
                'total_properties_count' => (clone $activeProperties)->count(),
                'for_sale_count' => (clone $activeProperties)->where('listing_type', 'sale')->count(),
                'for_rent_count' => (clone $activeProperties)->where('listing_type', 'rent')->count(),
                'agents' => (clone $activeAgents)->orderBy('name')->take(6)->get()
                    ->map(fn ($a) => $this->mapAgentCard($a))->values(),
                'properties' => (clone $activeProperties)->orderByDesc('published_at')->take(6)->get()
                    ->map(fn ($p) => $this->mapProperty($p, $lang, true))->values(),
                'seo' => SeoMeta::resolve($agency->metadata, $agency->seoFallback($lang)),
            ];
        });
    }

    private function mapAgentCard(PortalUser $agent): array
    {
        $rentCount = Property::where('agent_id', $agent->id)->where('listing_type', 'rent')->count();
        $sellCount = Property::where('agent_id', $agent->id)->where('listing_type', 'sale')->count();

        return [
            'slug' => $agent->slug,
            'name' => $agent->name,
            'avatar_url' => $agent->avatar ? asset('storage/'.$agent->avatar) : null,
            'years_of_experience' => $agent->years_of_experience,
            'preferred_areas' => $agent->preferred_areas ?? [],
            'rent_count' => $rentCount,
            'sell_count' => $sellCount,
        ];
    }

    private function mapAgencyCard(PortalUser $agency): array
    {
        return [
            'slug' => $agency->slug,
            'name' => $agency->displayName(),
            'logo_url' => $agency->avatar ? asset('storage/'.$agency->avatar) : null,
            'properties_count' => $agency->properties_count ?? 0,
            'office_address' => $agency->office_address,
            'email' => $agency->email,
            'phone' => $agency->phone,
        ];
    }
}
