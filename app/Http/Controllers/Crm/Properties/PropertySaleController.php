<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Models\Property;
use App\Services\PropertySaleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * @group CRM Properties — Sold / rented
 *
 * "Mark as sold / rented" on a listing card, and the paged buyer-lead picker it uses. The listing
 * moves to Sold Listings; the buyer lead moves to the won stage. See PropertySaleService.
 */
class PropertySaleController extends Controller
{
    use ScopesListings;

    public function __construct(private readonly PropertySaleService $sales)
    {
    }

    /**
     * Buyer leads
     *
     * Every lead the viewer sees in the CRM, this listing's enquirers first — 20 a page, searched on the server.
     *
     * @queryParam q string Name, email or phone. Example: sara
     * @queryParam page integer Example: 1
     */
    public function leads(Request $request, $id)
    {
        $property = $this->findAccessible($id);
        $search = mb_substr(trim((string) $request->input('q', '')), 0, 100);

        $page = $this->sales->buyerLeads($this->ownerId())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('leads.name', 'like', "%{$search}%")
                ->orWhere('leads.email', 'like', "%{$search}%")
                ->orWhere('leads.phone', 'like', "%{$search}%")))
            ->orderByRaw('CASE WHEN leads.property_id = ? THEN 0 ELSE 1 END', [$property->id])
            ->orderByDesc('leads.created_at')->orderByDesc('leads.id')
            ->simplePaginate(20, ['leads.id', 'leads.name', 'leads.email', 'leads.phone', 'leads.phone_country_code', 'leads.property_id']);

        return response()->json([
            'results' => collect($page->items())->map(fn ($lead) => [
                'id' => $lead->id,
                'name' => $lead->name ?: 'Unknown',
                'contact' => $lead->email ?: $lead->formatted_phone,
                'this_property' => $lead->property_id === $property->id,
            ]),
            'more' => $page->hasMorePages(),
        ]);
    }

    /**
     * Mark as sold / rented
     *
     * Multipart — the ownership document and the contract are required.
     *
     * @bodyParam type string required sold | rented. Example: sold
     * @bodyParam price number required Example: 1850000
     * @bodyParam sold_at string required Y-m-d. Example: 2026-10-09
     * @bodyParam buyer_mode string required existing | new. Example: existing
     * @bodyParam lead_id integer With buyer_mode=existing. Example: 101
     * @bodyParam ownership_document file required Title deed (PDF / JPG / PNG).
     * @bodyParam contract_document file required Form F / MOU, or the Ejari for a rental.
     */
    public function markSold(Request $request, $id)
    {
        $property = $this->findAccessible($id);
        abort_unless($this->isAdmin() || $this->viewer()->isApproved(), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in([Property::SOLD, Property::RENTED])],
            'price' => 'required|numeric|min:0|max:9999999999999',
            'sold_at' => 'required|date|before_or_equal:today',
            'rented_until' => 'nullable|date|after:sold_at',
            'commission' => 'nullable|numeric|min:0|max:9999999999999',
            'notes' => 'nullable|string|max:2000',
            'buyer_mode' => 'required|in:existing,new',
            'lead_id' => 'required_if:buyer_mode,existing|nullable|integer',
            'buyer.name' => 'required_if:buyer_mode,new|nullable|string|max:255',
            'buyer.email' => 'nullable|email|max:255',
            'buyer.phone' => 'nullable|string|max:30',
            'buyer.phone_country_code' => 'nullable|string|max:8',
            'ownership_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'contract_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents_password' => 'nullable|string|max:255',
        ], [
            'lead_id.required_if' => 'Pick the lead who bought / rented it, or add a new buyer.',
            'buyer.name.required_if' => "Enter the buyer's name.",
            'ownership_document.required' => 'Upload the property ownership document (title deed).',
            'contract_document.required' => $request->input('type') === Property::RENTED
                ? 'Upload the Ejari (official DLD tenancy contract).'
                : 'Upload the sale contract (Form F / MOU).',
            'ownership_document.mimes' => 'The ownership document must be a PDF, JPG or PNG.',
            'contract_document.mimes' => 'The contract must be a PDF, JPG or PNG.',
        ]);

        if ($data['buyer_mode'] === 'new' && empty($data['buyer']['email']) && empty($data['buyer']['phone'])) {
            return response()->json(['message' => 'Add an email or phone number for the buyer.', 'errors' => ['buyer.email' => ['Add an email or phone number for the buyer.']]], 422);
        }
        if ($data['buyer_mode'] === 'new') {
            $data['lead_id'] = null;
        }

        $this->sales->markSold($property, $data, $this->ownerId());

        return response()->json([
            'success' => true,
            'message' => 'Marked ' . $data['type'] . '. It is now off the website and listed under Sold Listings.',
            'redirect' => route('crm.app', 'sold-listings'),
        ]);
    }
}
