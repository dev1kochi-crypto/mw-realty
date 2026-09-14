<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\LeadStage;
use App\Services\Crm\LeadStageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/** Per-owner Stage master data — Super Admin manages their own via the shared "Admin" owner (see ScopesPortalOwner::effectiveOwnerId()). */
class LeadStageController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private readonly LeadStageService $stageService)
    {
    }

    public function index()
    {
        $ownerId = $this->effectiveOwnerId();
        $stages = LeadStage::forOwner($ownerId)->orderBy('order_index')->get();

        return view('portal.crm.master.stages.index', [
            'stages' => $stages,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

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

        // Super Admin adding a Stage from inside another owner's Lead (via the
        // "+ Add Stage" quick-add) needs it to land under *that* owner — not
        // Admin's own shared master data — so it shows up in their own
        // Master > Stage list too. The plain Master > Stage page never sends
        // this, so it keeps managing Admin's own data as before.
        if ($this->isAdmin() && $request->filled('owner_id')) {
            $ownerId = (int) $request->input('owner_id');
        }

        $stage = $this->stageService->createStage(
            $ownerId,
            $request->input('name'),
            $request->input('color'),
            $request->boolean('is_closed')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'stage' => ['id' => $stage->id, 'name' => $stage->name, 'color' => $stage->color],
            ]);
        }

        return back()->with('success', 'Stage added.');
    }

    protected function findOwned($id): LeadStage
    {
        return LeadStage::forOwner($this->effectiveOwnerId())->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $stage = $this->findOwned($id);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_stages', 'name')->where('portal_user_id', $stage->portal_user_id)->ignore($stage->id)],
            'color' => 'required|string|max:7',
            'is_closed' => 'nullable|boolean',
        ]);

        $stage->update([
            'name' => $request->input('name'),
            'color' => $request->input('color'),
            'is_closed' => $request->boolean('is_closed'),
        ]);

        return back()->with('success', 'Stage updated.');
    }

    public function destroy($id)
    {
        $stage = $this->findOwned($id);

        if ($stage->is_default) {
            return response()->json(['success' => false, 'message' => 'The default stage can\'t be deleted.'], 422);
        }
        if ($stage->leads()->exists()) {
            return response()->json(['success' => false, 'message' => 'Reassign leads off this stage before deleting it.'], 422);
        }

        $stage->delete();

        return response()->json(['success' => true]);
    }

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

    public function setDefault($id)
    {
        $stage = $this->findOwned($id);

        LeadStage::forOwner($stage->portal_user_id)->update(['is_default' => false]);
        $stage->update(['is_default' => true]);

        return response()->json(['success' => true]);
    }
}
