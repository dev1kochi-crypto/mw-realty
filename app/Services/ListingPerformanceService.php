<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyDailyStat;
use App\Models\Visitors\VisitorEvent;

/**
 * A listing's performance funnel for the portal's Listing Performance panel:
 * impressions → listing clicks → lead clicks → leads, the last 30 days day by day, and the
 * listing's leads (only those the viewer may see).
 */
class ListingPerformanceService
{
    public const DAYS = 30;

    public function __construct(private readonly ListingQualityService $quality)
    {
    }

    /** @param  int|null  $ownerId  Viewer's account (null = Super Admin, sees every lead). */
    public function for(Property $property, ?int $ownerId): array
    {
        $totals = PropertyDailyStat::where('property_id', $property->id)
            ->selectRaw('coalesce(sum(impressions), 0) as impressions, coalesce(sum(clicks), 0) as clicks, coalesce(sum(lead_clicks), 0) as lead_clicks, max(updated_at) as updated_at')
            ->first();

        $from = today()->subDays(self::DAYS - 1);
        $daily = PropertyDailyStat::where('property_id', $property->id)->where('date', '>=', $from)
            ->get(['date', 'impressions', 'clicks', 'lead_clicks'])->keyBy(fn ($row) => $row->date->toDateString());
        $series = collect(range(0, self::DAYS - 1))->map(function ($offset) use ($from, $daily) {
            $date = $from->copy()->addDays($offset);
            $row = $daily[$date->toDateString()] ?? null;

            return ['date' => $date, 'impressions' => (int) $row?->impressions, 'clicks' => (int) $row?->clicks, 'lead_clicks' => (int) $row?->lead_clicks];
        });

        $leads = Lead::forOwner($ownerId)->where('property_id', $property->id)->with(['stage', 'source', 'agent'])->latest()->get();
        $impressions = (int) $totals->impressions;
        $clicks = (int) $totals->clicks;

        return [
            'property' => $property,
            'funnel' => [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'lead_clicks' => (int) $totals->lead_clicks,
                'leads' => Lead::where('property_id', $property->id)->count(),
            ],
            'ctr' => $impressions ? round(100 * $clicks / $impressions, 1) : null,
            'updatedAt' => $totals->updated_at ? \Illuminate\Support\Carbon::parse($totals->updated_at) : null,
            'series' => $series,
            'seriesMax' => max(1, (int) $series->max('impressions'), (int) $series->max('clicks')),
            'last30' => [
                'impressions' => (int) $series->sum('impressions'),
                'clicks' => (int) $series->sum('clicks'),
                'lead_clicks' => (int) $series->sum('lead_clicks'),
            ],
            // Identified website visitors who opened this listing (no names — they may be another account's leads).
            'interestedVisitors' => VisitorEvent::where('property_id', $property->id)->where('type', VisitorEvent::PROPERTY_VIEW)
                ->whereNotNull('visitor_lead_id')->distinct()->count('visitor_lead_id'),
            'leads' => $leads,
            'quality' => $this->quality->score($property),
        ];
    }
}
