<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The portal-side Leads list/detail — Super Admin (cms guard) sees every lead,
 * an Agent/Company (portal guard) sees and can only act on their own.
 */
class LeadController extends Controller
{
    use ScopesPortalOwner;

    public function index(Request $request)
    {
        $ownerId = $this->ownerId();

        if ($owner = $this->owner()) {
            LeadStage::seedDefaultsFor($owner);
            LeadSource::seedDefaultsFor($owner);
        }

        $query = Lead::with(['property', 'owner', 'stage', 'source', 'tags'])
            ->forOwner($ownerId)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('stage_id')) {
            $query->where('stage_id', $request->input('stage_id'));
        }
        if ($request->filled('source_id')) {
            $query->where('source_id', $request->input('source_id'));
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('lead_tags.id', $request->input('tag_id')));
        }

        $leads = $query->paginate(15)->withQueryString();

        $stages = LeadStage::forOwner($ownerId)->orderBy('order_index')->get();
        $sources = LeadSource::forOwner($ownerId)->orderBy('order_index')->get();
        $tags = LeadTag::forOwner($ownerId)->orderBy('name')->get();

        $counts = Lead::forOwner($ownerId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total' => $counts->sum(),
            'new' => $counts->get('new', 0),
            'contacted' => $counts->get('contacted', 0),
            'closed' => $counts->get('closed', 0),
        ];

        return view('portal.crm.leads.index', [
            'leads' => $leads,
            'stages' => $stages,
            'sources' => $sources,
            'tags' => $tags,
            'stats' => $stats,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    protected function findOwned($id): Lead
    {
        return Lead::with(['property', 'owner', 'stage', 'source', 'tags'])
            ->forOwner($this->ownerId())
            ->findOrFail($id);
    }

    public function show($id)
    {
        $lead = $this->findOwned($id);
        $ownerId = $lead->portal_user_id;

        return view('portal.crm.leads.show', [
            'lead' => $lead,
            'stages' => LeadStage::forOwner($ownerId)->orderBy('order_index')->get(),
            'sources' => LeadSource::forOwner($ownerId)->orderBy('order_index')->get(),
            'tags' => LeadTag::forOwner($ownerId)->orderBy('name')->get(),
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $lead = $this->findOwned($id);
        $ownerId = $lead->portal_user_id;

        $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(['new', 'contacted', 'closed'])],
            'stage_id' => ['nullable', \Illuminate\Validation\Rule::exists('lead_stages', 'id')->where('portal_user_id', $ownerId)],
            'source_id' => ['nullable', \Illuminate\Validation\Rule::exists('lead_sources', 'id')->where('portal_user_id', $ownerId)],
            'tags' => 'nullable|array',
            'tags.*' => ['integer', \Illuminate\Validation\Rule::exists('lead_tags', 'id')->where('portal_user_id', $ownerId)],
            'notes' => 'nullable|string',
        ]);

        $lead->update([
            'status' => $request->input('status'),
            'stage_id' => $request->input('stage_id') ?: null,
            'source_id' => $request->input('source_id') ?: null,
            'notes' => $request->input('notes'),
        ]);

        $lead->tags()->sync($request->input('tags', []));

        return redirect()->route('portal.crm.leads.show', $lead->id)->with('success', 'Lead updated.');
    }
}
