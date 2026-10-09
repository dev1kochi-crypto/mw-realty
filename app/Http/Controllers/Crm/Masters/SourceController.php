<?php

namespace App\Http\Controllers\Crm\Masters;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Http\Resources\Crm\MasterItemResource;
use App\Models\LeadSource;
use App\Services\Crm\MasterDataLinks;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * @group CRM Master
 */
class SourceController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly MasterDataLinks $links)
    {
    }

    /**
     * List sources
     *
     * In display order, each with how many of your leads came from it.
     */
    public function index()
    {
        if ($owner = $this->owner()) {
            LeadSource::seedDefaultsFor($owner);
        }

        return MasterItemResource::collection(
            LeadSource::forOwner($this->effectiveOwnerId())
                ->withCount($this->links->countConstraint($this->ownerId()))
                ->orderBy('order_index')->get()
        );
    }

    /**
     * Add a source
     *
     * @bodyParam name string required Example: Instagram
     *
     * @response 200 {"success": true, "message": "Source added.", "source": {"id": 95, "name": "Instagram"}}
     */
    public function store(Request $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_sources', 'name')->whereIn('portal_user_id', LeadSource::usableOwnerIds($ownerId))],
        ]);

        $source = LeadSource::create([
            'portal_user_id' => $ownerId,
            'name' => $request->input('name'),
            'order_index' => (int) LeadSource::forOwner($ownerId)->max('order_index') + 1,
        ]);

        return response()->json(['success' => true, 'message' => 'Source added.', 'source' => ['id' => $source->id, 'name' => $source->name]]);
    }

    /**
     * Update a source
     *
     * @bodyParam name string required Example: Instagram
     */
    public function update(Request $request, $id)
    {
        $source = $this->findOwned($id);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_sources', 'name')->whereIn('portal_user_id', LeadSource::usableOwnerIds($source->portal_user_id))->ignore($source->id)],
        ]);

        $source->update(['name' => $request->input('name')]);

        return response()->json(['success' => true, 'message' => 'Source updated.', 'source' => new MasterItemResource($source->loadCount($this->links->countConstraint($this->ownerId())))]);
    }

    /**
     * Delete a source
     *
     * 422 with `in_use: true` while leads still came from it.
     *
     * @response 200 {"success": true, "message": "Source deleted."}
     */
    public function destroy($id)
    {
        $source = $this->findOwned($id);
        if ($source->leads()->exists()) {
            return response()->json(['success' => false, 'in_use' => true, 'message' => 'This source is still used by leads. Remove it from them first.'], 422);
        }
        $source->delete();

        return response()->json(['success' => true, 'message' => 'Source deleted.']);
    }

    /**
     * Reorder sources
     *
     * @bodyParam order integer[] required Your source ids, in the new order. Example: [22, 23, 24]
     *
     * @response 200 {"success": true}
     */
    public function reorder(Request $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:lead_sources,id',
        ]);

        foreach ($request->input('order') as $index => $id) {
            LeadSource::where('id', $id)->where('portal_user_id', $ownerId)->update(['order_index' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    /** Own items only — MW Realty's shared sources are read-only for agencies / agents. */
    private function findOwned($id): LeadSource
    {
        return LeadSource::ownedBy($this->effectiveOwnerId())->findOrFail($id);
    }
}
