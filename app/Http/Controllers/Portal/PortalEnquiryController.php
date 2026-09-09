<?php

namespace App\Http\Controllers\Portal;

use App\Models\CmsKit\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Shared CRM dashboard: Super Admin (cms guard) sees every enquiry;
 * an Agent/Company (portal guard) sees and can only act on their own.
 */
class PortalEnquiryController extends Controller
{
    protected function isAdmin(): bool
    {
        return (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    protected function ownerId(): ?int
    {
        return $this->isAdmin() ? null : Auth::guard('portal')->user()->id;
    }

    public function index(Request $request)
    {
        $query = Enquiry::with(['property', 'owner'])
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $enquiries = $query->paginate(15)->withQueryString();

        return view('portal.crm.index', ['enquiries' => $enquiries, 'isAdmin' => $this->isAdmin()]);
    }

    protected function findOwned($id): Enquiry
    {
        return Enquiry::with(['property', 'owner'])
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->findOrFail($id);
    }

    public function show($id)
    {
        $enquiry = $this->findOwned($id);
        return view('portal.crm.show', ['enquiry' => $enquiry, 'isAdmin' => $this->isAdmin()]);
    }

    public function update(Request $request, $id)
    {
        $enquiry = $this->findOwned($id);

        $request->validate([
            'status' => ['required', Rule::in(['new', 'contacted', 'closed'])],
            'notes' => 'nullable|string',
        ]);

        $enquiry->update([
            'status' => $request->input('status'),
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('portal.crm.show', $enquiry->id)->with('success', 'Enquiry updated.');
    }
}
