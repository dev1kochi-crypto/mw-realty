<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\LeadStage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/** Per-owner Stage master data — Super Admin has no owner row to manage this for (see index()). */
class LeadStageController extends Controller
{
    use ScopesPortalOwner;

    public function index()
    {
        if ($this->isAdmin()) {
            return redirect()->route('portal.dashboard')
                ->with('info', 'Stage/Tag/Source are managed per company or agent account — log in as (or impersonate) a specific account to manage theirs.');
        }

        $stages = LeadStage::forOwner($this->ownerId())->orderBy('order_index')->get();

        return view('portal.crm.master.stages.index', compact('stages'));
    }

    public function store(Request $request)
    {
        $ownerId = $this->ownerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_stages', 'name')->where('portal_user_id', $ownerId)],
            'color' => 'required|string|max:7',
            'is_closed' => 'nullable|boolean',
        ]);

        $nextOrder = (int) LeadStage::forOwner($ownerId)->max('order_index') + 1;

        LeadStage::create([
            'portal_user_id' => $ownerId,
            'name' => $request->input('name'),
            'color' => $request->input('color'),
            'order_index' => $nextOrder,
            'is_closed' => $request->boolean('is_closed'),
        ]);

        return back()->with('success', 'Stage added.');
    }

    protected function findOwned($id): LeadStage
    {
        return LeadStage::forOwner($this->ownerId())->findOrFail($id);
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
        $ownerId = $this->ownerId();
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
