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
        $tags = LeadTag::forOwner($ownerId)->with('owner:id,name,company_name,type')
            ->withCount(app(\App\Services\Crm\MasterDataLinks::class)->countConstraint($this->ownerId()))
            ->orderBy('name')->get();

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
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_tags', 'name')->whereIn('portal_user_id', LeadTag::usableOwnerIds($ownerId))],
            'color' => 'required|string|max:7',
        ]);

        $tag = LeadTag::create([
            'portal_user_id' => $ownerId,
            'name' => $request->input('name'),
            'color' => $request->input('color'),
        ]);

        // The Leads listing's Tags popup creates tags inline.
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Tag added.', 'tag' => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color]]);
        }

        return back()->with('success', 'Tag added.');
    }

    protected function findOwned($id): LeadTag
    {
        // Own items only — Super Admin's global tags are read-only for agencies / agents.
        return LeadTag::ownedBy($this->effectiveOwnerId())->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $tag = $this->findOwned($id);

        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('lead_tags', 'name')->whereIn('portal_user_id', LeadTag::usableOwnerIds($tag->portal_user_id))->ignore($tag->id)],
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
        if ($tag->leads()->exists()) {
            return response()->json(['success' => false, 'in_use' => true, 'message' => 'This tag is still used by leads. Remove it from them first.'], 422);
        }
        $tag->delete();

        return response()->json(['success' => true]);
    }
}
