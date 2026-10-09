<?php

namespace App\Http\Controllers\Crm\Masters;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Http\Resources\Crm\MasterItemResource;
use App\Models\LeadTag;
use App\Services\Crm\MasterDataLinks;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * @group CRM Master
 */
class TagController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly MasterDataLinks $links)
    {
    }

    /**
     * List tags
     *
     * Alphabetical, searched and paged on the server (50 per page).
     *
     * @queryParam search string Example: vip
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));

        return MasterItemResource::collection(
            LeadTag::forOwner($this->effectiveOwnerId())
                ->withCount($this->links->countConstraint($this->ownerId()))
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
                ->orderBy('name')->paginate(50)
        );
    }

    /**
     * Add a tag
     *
     * @bodyParam name string required Example: VIP
     * @bodyParam color string required Example: #ef4444
     *
     * @response 200 {"success": true, "message": "Tag added.", "tag": {"id": 40, "name": "VIP", "color": "#ef4444"}}
     */
    public function store(Request $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_tags', 'name')->whereIn('portal_user_id', LeadTag::usableOwnerIds($ownerId))],
            'color' => 'required|string|max:7',
        ]);

        $tag = LeadTag::create([
            'portal_user_id' => $ownerId,
            'name' => $request->input('name'),
            'color' => $request->input('color'),
        ]);

        return response()->json(['success' => true, 'message' => 'Tag added.', 'tag' => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color]]);
    }

    /**
     * Update a tag
     *
     * @bodyParam name string required Example: VIP
     * @bodyParam color string required Example: #ef4444
     */
    public function update(Request $request, $id)
    {
        $tag = $this->findOwned($id);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_tags', 'name')->whereIn('portal_user_id', LeadTag::usableOwnerIds($tag->portal_user_id))->ignore($tag->id)],
            'color' => 'required|string|max:7',
        ]);

        $tag->update(['name' => $request->input('name'), 'color' => $request->input('color')]);

        return response()->json(['success' => true, 'message' => 'Tag updated.', 'tag' => new MasterItemResource($tag->loadCount($this->links->countConstraint($this->ownerId())))]);
    }

    /**
     * Delete a tag
     *
     * 422 with `in_use: true` while leads still have it.
     *
     * @response 200 {"success": true, "message": "Tag deleted."}
     */
    public function destroy($id)
    {
        $tag = $this->findOwned($id);
        if ($tag->leads()->exists()) {
            return response()->json(['success' => false, 'in_use' => true, 'message' => 'This tag is still used by leads. Remove it from them first.'], 422);
        }
        $tag->delete();

        return response()->json(['success' => true, 'message' => 'Tag deleted.']);
    }

    /** Own items only — MW Realty's shared tags are read-only for agencies / agents. */
    private function findOwned($id): LeadTag
    {
        return LeadTag::ownedBy($this->effectiveOwnerId())->findOrFail($id);
    }
}
