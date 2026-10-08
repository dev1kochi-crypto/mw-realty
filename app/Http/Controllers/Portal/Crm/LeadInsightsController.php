<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\Lead;
use App\Models\Visitors\ChatConversation;
use App\Models\Visitors\VisitorEvent;
use App\Models\Visitors\VisitorLead;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CRM › Lead Insights: the viewer's own leads that came from the website, ranked by how engaged
 * they are — property views, time on site, searches, AI chats, last activity. Each opens the
 * lead's Insights tab. Same scoping as All Leads (Super Admin sees every lead).
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
            ->paginate(20)->withQueryString();

        $visitorIds = (clone $base)->select('visitor_lead_id');
        $totals = [
            'leads' => (clone $base)->count(),
            'property_views' => VisitorEvent::whereIn('visitor_lead_id', $visitorIds)->where('type', VisitorEvent::PROPERTY_VIEW)->count(),
            'seconds' => (int) VisitorEvent::whereIn('visitor_lead_id', $visitorIds)->sum('duration_seconds'),
            'chats' => ChatConversation::whereIn('visitor_lead_id', $visitorIds)->where('message_count', '>', 0)->count(),
            'active_week' => VisitorLead::whereIn('id', $visitorIds)->where('last_seen_at', '>=', now()->subDays(7))->count(),
        ];

        return view('portal.crm.lead-insights.index', [
            'leads' => $leads,
            'totals' => $totals,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'search' => $search,
            'isAdmin' => $this->isAdmin(),
        ]);
    }
}
