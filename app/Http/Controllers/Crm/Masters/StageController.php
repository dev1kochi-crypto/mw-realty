<?php

namespace App\Http\Controllers\Crm\Masters;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Http\Resources\Crm\MasterItemResource;
use App\Models\LeadStage;
use App\Services\Crm\LeadStageService;
use App\Services\Crm\MasterDataLinks;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * @group CRM Master
 *
 * The stages, tags and sources used on leads — your own, plus MW Realty's shared ones
 * (`is_global: true`), which you can use but not change. A Super Admin manages the shared ones
 * (through the shared "Admin" owner, see OwnerContext::effectiveOwnerId()). Approved accounts only.
 */
class StageController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly LeadStageService $stageService, private readonly MasterDataLinks $links)
    {
    }

    /**
     * List stages
     *
     * In pipeline order, each with how many of your leads are in it.
     */
    public function index()
    {
        if ($owner = $this->owner()) {
            LeadStage::seedDefaultsFor($owner);
        }

        return MasterItemResource::collection(
            LeadStage::forOwner($this->effectiveOwnerId())
                ->withCount($this->links->countConstraint($this->ownerId()))
                ->orderBy('order_index')->get()
        );
    }

    /**
     * Add a stage
     *
     * @bodyParam name string required Example: Viewing booked
     * @bodyParam color string Hex colour. Example: #0ea5e9
     * @bodyParam is_closed boolean Leads in this stage count as closed. Example: false
     * @bodyParam owner_id integer Super Admin only — add it to this account's stages instead (quick-add from that account's lead). Example: 12
     *
     * @response 200 {"success": true, "message": "Stage added.", "stage": {"id": 90, "name": "Viewing booked", "color": "#0ea5e9"}}
     */
    public function store(Request $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_closed' => ['nullable', 'boolean'],
            'owner_id' => ['nullable', 'integer', 'exists:portal_users,id'],
        ]);

        if ($this->isAdmin() && $request->filled('owner_id')) {
            $ownerId = (int) $request->input('owner_id');
        }

        $stage = $this->stageService->createStage($ownerId, $request->input('name'), $request->input('color'), $request->boolean('is_closed'));

        return response()->json([
            'success' => true,
            'message' => 'Stage added.',
            'stage' => ['id' => $stage->id, 'name' => $stage->name, 'color' => $stage->color],
        ]);
    }

    /**
     * Update a stage
     *
     * @bodyParam name string required Example: Viewing booked
     * @bodyParam color string required Example: #0ea5e9
     * @bodyParam is_closed boolean Example: false
     */
    public function update(Request $request, $id)
    {
        $stage = $this->findOwned($id);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_stages', 'name')->whereIn('portal_user_id', LeadStage::usableOwnerIds($stage->portal_user_id))->ignore($stage->id)],
            'color' => 'required|string|max:7',
            'is_closed' => 'nullable|boolean',
        ]);

        $stage->update([
            'name' => $request->input('name'),
            'color' => $request->input('color'),
            'is_closed' => $request->boolean('is_closed'),
        ]);

        return response()->json(['success' => true, 'message' => 'Stage updated.', 'stage' => new MasterItemResource($stage->loadCount($this->links->countConstraint($this->ownerId())))]);
    }

    /**
     * Delete a stage
     *
     * 422 with `in_use: true` while leads are still in it — take it off them first
     * (`POST /api/crm/master/stages/{id}/leads/remove`). The default stage can't be deleted.
     *
     * @response 200 {"success": true, "message": "Stage deleted."}
     */
    public function destroy($id)
    {
        $stage = $this->findOwned($id);

        if ($stage->is_default) {
            return response()->json(['success' => false, 'message' => 'The default stage can\'t be deleted.'], 422);
        }
        if ($stage->leads()->exists()) {
            return response()->json(['success' => false, 'in_use' => true, 'message' => 'This stage is still used by leads. Remove it from them first.'], 422);
        }

        $stage->delete();

        return response()->json(['success' => true, 'message' => 'Stage deleted.']);
    }

    /**
     * Reorder stages
     *
     * @bodyParam order integer[] required Your stage ids, in the new order. Example: [26, 27, 28]
     *
     * @response 200 {"success": true}
     */
    public function reorder(Request $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:lead_stages,id',
        ]);

        foreach ($request->input('order') as $index => $id) {
            LeadStage::where('id', $id)->where('portal_user_id', $ownerId)->update(['order_index' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Make default
     *
     * New leads start in this stage.
     *
     * @response 200 {"success": true, "message": "Default stage updated."}
     */
    public function setDefault($id)
    {
        $stage = $this->findOwned($id);

        LeadStage::ownedBy($stage->portal_user_id)->update(['is_default' => false]);
        $stage->update(['is_default' => true]);

        return response()->json(['success' => true, 'message' => 'Default stage updated.']);
    }

    /** Own items only — MW Realty's shared stages are read-only for agencies / agents. */
    private function findOwned($id): LeadStage
    {
        return LeadStage::ownedBy($this->effectiveOwnerId())->findOrFail($id);
    }
}
