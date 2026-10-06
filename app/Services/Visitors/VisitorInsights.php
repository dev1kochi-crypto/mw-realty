<?php

namespace App\Services\Visitors;

use App\Models\Property;
use App\Models\Visitors\VisitorEvent;
use App\Models\Visitors\VisitorLead;
use Illuminate\Support\Facades\DB;

/**
 * Everything shown on a website lead's Insights panel — Super Admin's Website Leads page and the
 * CRM lead page's Insights tab (resources/views/visitor-insights/_panel.blade.php).
 */
class VisitorInsights
{
    private const TIMELINE_LIMIT = 200;

    public function for(VisitorLead $lead): array
    {
        $events = VisitorEvent::where('visitor_lead_id', $lead->id);
        $counts = (clone $events)->selectRaw('type, count(*) as total, sum(duration_seconds) as seconds')
            ->groupBy('type')->get()->keyBy('type');
        $count = fn (string $type) => (int) ($counts[$type]->total ?? 0);

        $topProperties = (clone $events)->where('type', VisitorEvent::PROPERTY_VIEW)->whereNotNull('property_id')
            ->select('property_id', DB::raw('count(*) as views'), DB::raw('sum(duration_seconds) as seconds'), DB::raw('max(created_at) as last_viewed_at'))
            ->groupBy('property_id')->orderByDesc('seconds')->orderByDesc('views')->limit(10)->get();
        $properties = Property::with('owner')->whereIn('id', $topProperties->pluck('property_id'))->get()->keyBy('id');

        $customer = $lead->customer;

        return [
            'lead' => $lead,
            'stats' => [
                'property_views' => $count(VisitorEvent::PROPERTY_VIEW),
                'properties_viewed' => (clone $events)->where('type', VisitorEvent::PROPERTY_VIEW)->distinct()->count('property_id'),
                'property_seconds' => (int) ($counts[VisitorEvent::PROPERTY_VIEW]->seconds ?? 0),
                'page_views' => $count(VisitorEvent::PAGE_VIEW) + $count(VisitorEvent::PROPERTY_VIEW) + $count(VisitorEvent::SEARCH),
                'site_seconds' => (int) $counts->sum('seconds'),
                'searches' => $count(VisitorEvent::SEARCH),
                'favorites' => $customer ? $customer->wishlistProperties()->count() : $count(VisitorEvent::FAVORITE_ADDED),
                'saved_searches' => $customer ? $customer->savedSearches()->count() : $count(VisitorEvent::SAVED_SEARCH),
                'chats' => $lead->conversations()->where('message_count', '>', 0)->count(),
                'enquiries' => $count(VisitorEvent::ENQUIRY),
                'first_seen' => (clone $events)->min('created_at') ?? $lead->created_at,
                'last_seen' => $lead->last_seen_at ?? $lead->updated_at,
            ],
            'topProperties' => $topProperties->map(fn ($row) => [
                'property' => $properties[$row->property_id] ?? null,
                'views' => (int) $row->views,
                'seconds' => (int) $row->seconds,
                'last_viewed_at' => \Illuminate\Support\Carbon::parse($row->last_viewed_at),
            ])->filter(fn ($row) => $row['property'])->values(),
            'favorites' => $customer
                ? $customer->wishlistProperties()->latest('property_wishlists.created_at')->limit(20)->get()
                : collect(),
            'savedSearches' => $customer ? $customer->savedSearches()->latest()->limit(20)->get() : collect(),
            'conversations' => $lead->conversations()->where('message_count', '>', 0)->with('messages')->latest('last_message_at')->get(),
            'crmLeads' => $lead->crmLeads()->with(['owner', 'agent', 'property'])->latest()->get(),
            // What they search for: the most used listing filters (e.g. "Bedrooms: 2" ×3).
            'searchInterests' => (clone $events)->whereIn('type', [VisitorEvent::SEARCH, VisitorEvent::SAVED_SEARCH])
                ->latest('id')->limit(200)->pluck('meta')
                ->flatMap(fn ($meta) => collect($meta['filters'] ?? [])
                    ->filter(fn ($value, $key) => is_scalar($value) && $value !== '' && !in_array($key, ['page', 'sort', 'lang', 'view'], true))
                    ->map(fn ($value, $key) => ucfirst(str_replace(['_', '-'], ' ', $key)) . ': ' . $value)
                    ->values())
                ->countBy()->sortDesc()->take(10),
            'timeline' => (clone $events)->with('property')->latest('id')->limit(self::TIMELINE_LIMIT)->get(),
            'timelineLimit' => self::TIMELINE_LIMIT,
        ];
    }

    /**
     * Rough engagement level from the stats — [label, tone]: listing views and time count, an
     * AI chat or a form enquiry counts more.
     */
    public static function engagement(array $stats): array
    {
        $score = $stats['property_views'] * 2 + $stats['chats'] * 3 + $stats['enquiries'] * 5
            + $stats['favorites'] * 3 + $stats['saved_searches'] * 3 + intdiv($stats['site_seconds'], 60);

        return match (true) {
            $score >= 15 => ['Hot', 'accent'],
            $score >= 6 => ['Warm', 'warning'],
            default => ['Cold', 'muted'],
        };
    }

    /** 75 → "1m 15s", 3900 → "1h 5m". */
    public static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'm ' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT) . 's';
        }

        return intdiv($seconds, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm';
    }
}
