<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Services\PropertySaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Mark as sold / rented" on a listing card, the paged buyer-lead picker it uses, and the
 * Sold Listings menu. Same scoping as the Properties screens (Super Admin = every listing).
 */
class PortalSoldPropertyController extends PortalPropertyController
{
    protected const SOLD_PER_PAGE = 20;

    public function __construct(private readonly PropertySaleService $sales)
    {
    }

    public function soldIndex(Request $request)
    {
        $search = mb_substr(trim((string) $request->input('q', '')), 0, 100);
        $type = in_array($request->input('type'), [Property::SOLD, Property::RENTED], true) ? $request->input('type') : null;

        $scoped = fn () => Property::query()->soldOrRented()
            ->when($this->ownerId(), fn ($q) => $q->accessibleBy($this->viewer()));

        $listings = $scoped()
            ->when($type, fn ($q) => $q->where('sold_type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->portalSearch($search, $this->isAdmin())
                ->orWhereIn('sold_lead_id', \App\Models\Lead::where('name', 'like', '%' . $search . '%')->select('id'))))
            ->with(['soldLead:id,name,email,phone,phone_country_code', 'soldAgent:id,name', 'owner:id,name,company_name,type'])
            ->orderByDesc('sold_at')->orderByDesc('id')
            ->paginate(self::SOLD_PER_PAGE)->withQueryString();

        $totals = $scoped()->selectRaw('sold_type, count(*) as total, COALESCE(SUM(sold_price), 0) as value')
            ->groupBy('sold_type')->get()->keyBy('sold_type');

        return view('portal.sold.index', [
            'listings' => $listings,
            'search' => $search,
            'type' => $type,
            'totals' => $totals,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    /** Buyer picker: every lead the viewer sees in the CRM, this listing's enquirers first — 20 a page, searched on the server. */
    public function leadOptions(Request $request, $id)
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
        ], [
            'lead_id.required_if' => 'Pick the lead who bought / rented it, or add a new buyer.',
            'buyer.name.required_if' => "Enter the buyer's name.",
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
            'redirect' => route('portal.sold.index'),
        ]);
    }

    public function revert($id)
    {
        $property = $this->findAccessible($id);
        abort_unless($property->isSold(), 404);
        abort_unless($this->isAdmin() || $this->viewer()->isApproved(), 403);

        $this->sales->revert($property);

        return response()->json(['success' => true]);
    }
}
