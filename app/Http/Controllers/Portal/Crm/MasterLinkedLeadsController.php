<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Services\Crm\MasterDataLinks;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * "N leads" on Master › Stage / Tag / Source: lists the leads using an item (20 a page, searched on
 * the server) and takes it off selected / all of them, so the item can then be deleted. Only for
 * items the viewer may delete — their own, or Super Admin's global ones for Super Admin.
 */
class MasterLinkedLeadsController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly MasterDataLinks $links)
    {
    }

    private function item(string $type, $id)
    {
        $model = MasterDataLinks::TYPES[$type]['model'];

        return $model::ownedBy($this->effectiveOwnerId())->findOrFail($id);
    }

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
                'date' => $lead->created_at?->format('d M Y'),
                'url' => route('portal.crm.leads.show', $lead->id),
            ]),
            'total' => $page->total(),
            'more' => $page->hasMorePages(),
        ]);
    }

    public function remove(Request $request, string $type, $id)
    {
        $item = $this->item($type, $id);
        $data = $request->validate([
            'all' => 'sometimes|boolean',
            'ids' => 'required_without:all|array|max:500',
            'ids.*' => 'integer',
        ]);

        $removed = $this->links->remove($type, $item, $this->ownerId(), $request->boolean('all') ? null : $data['ids']);
        $remaining = $this->links->leads($type, $item, $this->ownerId())->count();

        return response()->json([
            'success' => true,
            'removed' => $removed,
            'remaining' => $remaining,
            'message' => "Removed from {$removed} " . \Illuminate\Support\Str::plural('lead', $removed) . '.',
        ]);
    }
}
