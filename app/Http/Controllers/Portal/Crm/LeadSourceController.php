<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\LeadSource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class LeadSourceController extends Controller
{
    use ScopesPortalOwner;

    public function index()
    {
        if ($this->isAdmin()) {
            return redirect()->route('portal.dashboard')
                ->with('info', 'Stage/Tag/Source are managed per company or agent account — log in as (or impersonate) a specific account to manage theirs.');
        }

        $sources = LeadSource::forOwner($this->ownerId())->orderBy('order_index')->get();

        return view('portal.crm.master.sources.index', compact('sources'));
    }

    public function store(Request $request)
    {
        $ownerId = $this->ownerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_sources', 'name')->where('portal_user_id', $ownerId)],
        ]);

        $nextOrder = (int) LeadSource::forOwner($ownerId)->max('order_index') + 1;

        LeadSource::create([
            'portal_user_id' => $ownerId,
            'name' => $request->input('name'),
            'order_index' => $nextOrder,
        ]);

        return back()->with('success', 'Source added.');
    }

    protected function findOwned($id): LeadSource
    {
        return LeadSource::forOwner($this->ownerId())->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $source = $this->findOwned($id);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_sources', 'name')->where('portal_user_id', $source->portal_user_id)->ignore($source->id)],
        ]);

        $source->update(['name' => $request->input('name')]);

        return back()->with('success', 'Source updated.');
    }

    public function destroy($id)
    {
        $source = $this->findOwned($id);

        if ($source->leads()->exists()) {
            return response()->json(['success' => false, 'message' => 'Reassign leads off this source before deleting it.'], 422);
        }

        $source->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $ownerId = $this->ownerId();
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
}
