<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Services\ListingPerformanceService;
use Illuminate\Routing\Controller;

/**
 * @group CRM Properties — Performance
 */
class PropertyInsightsController extends Controller
{
    use ScopesListings;

    /**
     * Listing performance
     *
     * The side panel behind a card's Insights / Leads / quality ring: performance funnel
     * (impressions → listing clicks → lead clicks → leads), the last 30 days day by day, quality
     * score with what to improve, who works the listing, and its leads (only those the viewer sees).
     */
    public function __invoke($id, ListingPerformanceService $performance)
    {
        $property = $this->findAccessible($id);
        $data = $performance->for($property, $this->ownerId());
        $quality = $data['quality'];

        return response()->json([
            'property' => [
                'id' => $property->id,
                'segment' => $property->segment,
                'title' => $property->getTranslation('title') ?: $property->reference_no,
                'thumb' => $property->galleryImages()[0]['url'] ?? null,
                'reference_no' => $property->reference_no,
                'price' => $property->price ? number_format($property->price) . ' ' . $property->currency : 'Price on request',
                'status' => (bool) $property->status,
                'status_label' => $property->isSold() ? ucfirst($property->sold_type ?? 'sold') : ($property->status ? 'Active' : 'Inactive'),
                'featured' => (bool) $property->featured,
                'url' => $property->slug ? url('/property-details/' . $property->slug) : null,
            ],
            'funnel' => $data['funnel'],
            'last30' => $data['last30'],
            'updated_at' => $data['updatedAt']?->toIso8601String(),
            'series' => $data['series']->map(fn ($d) => ['date' => $d['date']->toDateString(), 'impressions' => $d['impressions'], 'clicks' => $d['clicks']])->values(),
            'series_max' => $data['seriesMax'],
            'days' => ListingPerformanceService::DAYS,
            'interested_visitors' => $data['interestedVisitors'],
            'quality' => $quality,
            'fixes' => collect($quality['groups'])->flatten(1)->filter(fn ($c) => $c['tip'])->sortByDesc(fn ($c) => $c['max'] - $c['points'])->take(3)
                ->map(fn ($c) => ['tip' => $c['tip'], 'gain' => $c['max'] - $c['points']])->values(),
            'people' => [
                ['Assigned to', 'fa-user-tie', $property->agent?->name ?? ($property->owner?->type === 'agent' ? $property->owner->name : ($property->owner?->displayName() ?? 'MW Realty'))],
                ['Created by', 'fa-user-pen', $property->createdByName() ?? '—'],
                ['Updated by', 'fa-user-gear', $property->updatedByName() ?? '—'],
                ['Last updated', 'fa-calendar-check', $property->updated_at->format('M d, Y, h:i A')],
            ],
            'tiles' => array_values(array_filter([
                ['fa-toggle-on', 'Status', $property->isSold() ? ucfirst($property->sold_type ?? 'sold') : ($property->status ? 'Active' : 'Inactive')],
                ['fa-file-shield', 'Permit', $property->complianceLabel()],
                ['fa-star', 'Exposure', $property->featured ? 'Premium' . ($property->featured_until ? ' · ' . $property->featured_until->format('d M') : '') : 'Standard'],
                ['fa-user-tie', 'Agent', $property->agent?->name ?? ($property->owner?->type === 'agent' ? $property->owner->name : 'Agency listing')],
                $this->isAdmin() ? ['fa-building', 'Owner', $property->owner?->displayName() ?? 'MW Realty'] : null,
                ['far fa-calendar', 'Listed', $property->created_at->format('d M Y')],
                $property->permit_number ? ['fa-hashtag', 'Permit no.', $property->permit_number] : null,
                ['fa-gauge-high', 'Quality', $quality['score'] . '/100'],
            ])),
            'leads' => $data['leads']->map(fn ($lead) => [
                'id' => $lead->id,
                'name' => $lead->name ?: 'Unknown',
                'source' => $lead->source?->name ?? '—',
                'agent' => $lead->agent?->name,
                'created_at' => $lead->created_at->toIso8601String(),
                'stage' => $lead->stage ? ['name' => $lead->stage->name, 'color' => $lead->stage->color ?: '#6b7094'] : null,
            ])->values(),
        ]);
    }
}
