<?php

namespace App\Http\Controllers\Crm\Masters;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Services\Crm\MasterDataLinks;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * @group CRM Master
 *
 * Which of your leads use a stage / tag / source, and taking it off them — so an item still in
 * use can then be deleted.
 */
class LinkedLeadsController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly MasterDataLinks $links)
    {
    }

    /**
     * Leads using an item
     *
     * 20 per page (`more: true` = load the next page).
     *
     * @urlParam type string required stages, tags or sources. Example: stages
     * @urlParam id integer required Example: 26
     * @queryParam q string Name, email or phone. Example: sara
     * @queryParam page integer Example: 1
     *
     * @response 200 {"results": [{"id": 101, "name": "John Smith", "contact": "john@example.com", "owner": null, "created_at": "2026-10-01T09:30:00+00:00"}], "total": 1, "more": false}
     */
    public function index(Request $request, string $type, $id)
    {
        $item = $this->item($type, $id);
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $page = $this->links->leads($type, $item, $this->ownerId())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('leads.name', 'like', "%{$search}%")
                ->orWhere('leads.email', 'like', "%{$search}%")->orWhere('leads.phone', 'like', "%{$search}%")))
            ->with('owner:id,name,company_name,type')
            ->latest('leads.id')
            ->paginate(20, ['leads.id', 'leads.name', 'leads.email', 'leads.phone', 'leads.phone_country_code', 'leads.portal_user_id', 'leads.created_at']);

        return response()->json([
            'results' => collect($page->items())->map(fn ($lead) => [
                'id' => $lead->id,
                'name' => $lead->name ?: 'Unknown',
                'contact' => $lead->email ?: $lead->formatted_phone,
                'owner' => $this->isAdmin() ? $lead->owner?->displayName() : null,
                'created_at' => $lead->created_at?->toIso8601String(),
            ]),
            'total' => $page->total(),
            'more' => $page->hasMorePages(),
        ]);
    }

    /**
     * Take an item off leads
     *
     * Stage / source changes land in each lead's timeline.
     *
     * @urlParam type string required stages, tags or sources. Example: stages
     * @urlParam id integer required Example: 26
     * @bodyParam all boolean Every one of your leads using it. Example: true
     * @bodyParam ids integer[] Or just these leads. Example: [101, 102]
     *
     * @response 200 {"success": true, "removed": 2, "remaining": 0, "message": "Removed from 2 leads."}
     */
    public function remove(Request $request, string $type, $id)
    {
        $item = $this->item($type, $id);
        $data = $request->validate([
            'all' => 'sometimes|boolean',
            'ids' => 'required_without:all|array|max:500',
            'ids.*' => 'integer',
        ]);

        $removed = $this->links->remove($type, $item, $this->ownerId(), $request->boolean('all') ? null : $data['ids']);

        return response()->json([
            'success' => true,
            'removed' => $removed,
            'remaining' => $this->links->leads($type, $item, $this->ownerId())->count(),
            'message' => "Removed from {$removed} " . Str::plural('lead', $removed) . '.',
        ]);
    }

    /** Own items only — a shared MW Realty item can't be taken off leads by an agency / agent. */
    private function item(string $type, $id)
    {
        $model = MasterDataLinks::TYPES[$type]['model'];

        return $model::ownedBy($this->effectiveOwnerId())->findOrFail($id);
    }
}
