<?php

namespace App\Http\Controllers\Crm\Leads;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\Lead;
use App\Models\Visitors\ChatConversation;
use App\Models\Visitors\VisitorEvent;
use App\Models\Visitors\VisitorLead;
use App\Services\Visitors\VisitorInsights;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group CRM Lead Insights
 *
 * Your leads that came from the website, ranked by how engaged they are — property views, time
 * on site, searches, AI chats, last activity. Same scoping as Leads (Super Admin sees every lead).
 */
class LeadInsightsController extends Controller
{
    use ScopesPortalOwner;

    public const SORTS = [
        'active' => 'Recently active',
        'time' => 'Most time on site',
        'views' => 'Most property views',
        'chats' => 'Most AI chats',
    ];

    /**
     * Lead insights
     *
     * 20 per page, plus totals across all of them and the sort options.
     *
     * @queryParam sort string active, time, views or chats. Example: active
     * @queryParam search string Name, email or phone. Example: sara
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'active';
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);

        $base = Lead::forOwner($this->ownerId())->whereNotNull('visitor_lead_id');

        $leads = (clone $base)
            ->with(['owner', 'agent', 'stage', 'property'])
            ->withVisitorStats()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->orderByDesc(match ($sort) {
                'time' => 'visitor_seconds',
                'views' => 'visitor_property_views',
                'chats' => 'visitor_chats',
                default => 'visitor_last_seen',
            })
            ->orderByDesc('leads.id')
            ->paginate(20);

        $visitorIds = (clone $base)->select('visitor_lead_id');

        return response()->json([
            'data' => collect($leads->items())->map(function (Lead $lead) {
                $stats = [
                    'property_views' => (int) $lead->visitor_property_views,
                    'properties' => (int) $lead->visitor_properties,
                    'seconds' => (int) $lead->visitor_seconds,
                    'searches' => (int) $lead->visitor_searches,
                    'chats' => (int) $lead->visitor_chats,
                    'last_seen' => $lead->visitor_last_seen ? \Illuminate\Support\Carbon::parse($lead->visitor_last_seen)->toIso8601String() : null,
                ];
                [$engagement, $tone] = VisitorInsights::engagement([
                    'property_views' => $stats['property_views'], 'chats' => $stats['chats'], 'enquiries' => 0,
                    'favorites' => 0, 'saved_searches' => 0, 'site_seconds' => $stats['seconds'],
                ]);

                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'formatted_phone' => $lead->formatted_phone,
                    'stage' => $lead->stage ? ['id' => $lead->stage->id, 'name' => $lead->stage->name, 'color' => $lead->stage->color] : null,
                    'owner' => $lead->owner ? ['id' => $lead->owner->id, 'name' => $lead->owner->displayName()] : null,
                    'agent' => $lead->agent ? ['id' => $lead->agent->id, 'name' => $lead->agent->name] : null,
                    'property' => $lead->property ? ['id' => $lead->property->id, 'title' => $lead->property->getTranslation('title')] : null,
                    'stats' => $stats,
                    'engagement' => ['label' => $engagement, 'tone' => $tone],
                ];
            }),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total' => $leads->total(),
            ],
            'totals' => [
                'leads' => (clone $base)->count(),
                'property_views' => VisitorEvent::whereIn('visitor_lead_id', $visitorIds)->where('type', VisitorEvent::PROPERTY_VIEW)->count(),
                'seconds' => (int) VisitorEvent::whereIn('visitor_lead_id', $visitorIds)->sum('duration_seconds'),
                'chats' => ChatConversation::whereIn('visitor_lead_id', $visitorIds)->where('message_count', '>', 0)->count(),
                'active_week' => VisitorLead::whereIn('id', $visitorIds)->where('last_seen_at', '>=', now()->subDays(7))->count(),
            ],
            'sorts' => collect(self::SORTS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'is_admin' => $this->isAdmin(),
        ]);
    }
}
