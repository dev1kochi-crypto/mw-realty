<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\LeadTag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class LeadTagController extends Controller
{
    use ScopesPortalOwner;

    public function index()
    {
        $ownerId = $this->effectiveOwnerId();
        $tags = LeadTag::forOwner($ownerId)->orderBy('name')->get();

        return view('portal.crm.master.tags.index', [
            'tags' => $tags,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $ownerId = $this->effectiveOwnerId();
        abort_if(!$ownerId, 403);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_tags', 'name')->where('portal_user_id', $ownerId)],
            'color' => 'required|string|max:7',
        ]);

        LeadTag::create([
            'portal_user_id' => $ownerId,
            'name' => $request->input('name'),
            'color' => $request->input('color'),
        ]);

        return back()->with('success', 'Tag added.');
    }

    protected function findOwned($id): LeadTag
    {
        return LeadTag::forOwner($this->effectiveOwnerId())->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $tag = $this->findOwned($id);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_tags', 'name')->where('portal_user_id', $tag->portal_user_id)->ignore($tag->id)],
            'color' => 'required|string|max:7',
        ]);

        $tag->update([
            'name' => $request->input('name'),
            'color' => $request->input('color'),
        ]);

        return back()->with('success', 'Tag updated.');
    }

    public function destroy($id)
    {
        $tag = $this->findOwned($id);
        $tag->delete();

        return response()->json(['success' => true]);
    }
}
