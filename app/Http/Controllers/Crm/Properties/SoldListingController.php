<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Models\Lead;
use App\Models\Property;
use App\Services\PropertySaleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * @group CRM Sold Listings
 *
 * Listings marked sold or rented (PropertySaleController::markSold), their proof documents, and
 * reverting one to put it back on the market. Same scoping as Properties (Super Admin = every listing).
 */
class SoldListingController extends Controller
{
    use ScopesListings;

    private const PER_PAGE = 20;

    public function __construct(private readonly PropertySaleService $sales)
    {
    }

    /**
     * List sold / rented listings
     *
     * Most recent sale first, 20 per page, with the sold / rented totals.
     *
     * @queryParam q string Title, ref no, address, city or buyer name. Example: marina
     * @queryParam type string sold | rented. Example: sold
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $search = mb_substr(trim((string) $request->input('q', '')), 0, 100);
        $type = in_array($request->input('type'), [Property::SOLD, Property::RENTED], true) ? $request->input('type') : null;

        $scoped = fn () => Property::query()->soldOrRented()
            ->when($this->ownerId(), fn ($q) => $q->accessibleBy($this->viewer()));

        $listings = $scoped()
            ->when($type, fn ($q) => $q->where('sold_type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->portalSearch($search, $this->isAdmin())
                ->orWhereIn('sold_lead_id', Lead::where('name', 'like', '%' . $search . '%')->select('id'))))
            ->with(['soldLead:id,name,email,phone,phone_country_code', 'soldAgent:id,name', 'owner:id,name,company_name,type'])
            ->orderByDesc('sold_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $totals = $scoped()->selectRaw('sold_type, count(*) as total, COALESCE(SUM(sold_price), 0) as value')
            ->groupBy('sold_type')->get()->keyBy('sold_type');

        return response()->json([
            'data' => collect($listings->items())->map(fn (Property $p) => [
                'id' => $p->id,
                'segment' => $p->segment,
                'title' => $p->getTranslation('title') ?: 'Property',
                'thumb' => $p->galleryImages()[0]['url'] ?? null,
                'reference_no' => $p->reference_no,
                'type_label' => $p->filterLabel('property_type'),
                'sold_type' => $p->sold_type,
                'rented_until' => $p->rented_until?->toDateString(),
                'currency' => $p->currency ?: 'AED',
                'sold_price' => (float) $p->sold_price,
                'price' => $p->price ? (float) $p->price : null,
                'commission' => $p->sold_commission ? (float) $p->sold_commission : null,
                'sold_at' => $p->sold_at?->toDateString(),
                'lead' => $p->soldLead ? ['id' => $p->soldLead->id, 'name' => $p->soldLead->name ?: 'Lead #' . $p->soldLead->id, 'contact' => $p->soldLead->email ?: $p->soldLead->formatted_phone] : null,
                'agent' => $p->soldAgent?->name,
                'owner' => $p->owner?->displayName() ?? 'MW Realty',
                'has_ownership_document' => (bool) $p->sold_ownership_document,
                'has_contract_document' => (bool) $p->sold_contract_document,
                'documents_password' => $p->sold_documents_password,
                'notes' => $p->sold_notes,
            ]),
            'meta' => [
                'current_page' => $listings->currentPage(), 'last_page' => $listings->lastPage(), 'total' => $listings->total(),
                'from' => $listings->firstItem(), 'to' => $listings->lastItem(),
            ],
            'search' => $search,
            'type' => $type,
            'totals' => [
                'sold' => ['total' => (int) ($totals[Property::SOLD]->total ?? 0), 'value' => (float) ($totals[Property::SOLD]->value ?? 0)],
                'rented' => ['total' => (int) ($totals[Property::RENTED]->total ?? 0), 'value' => (float) ($totals[Property::RENTED]->value ?? 0)],
            ],
            'is_admin' => $this->isAdmin(),
        ]);
    }

    /**
     * Proof document
     *
     * The ownership document (title deed) or the contract (Form F / MOU, or the Ejari) — private
     * storage, same access as the listing itself.
     */
    public function document($id, string $kind)
    {
        $property = $this->findAccessible($id);
        $path = match ($kind) {
            'ownership' => $property->sold_ownership_document,
            'contract' => $property->sold_contract_document,
            default => null,
        };
        abort_unless($path && str_starts_with($path, Property::SALE_DOCUMENTS_DIRECTORY . '/') && Storage::disk('kyc')->exists($path), 404);

        $name = ($property->reference_no ?: 'property-' . $property->id) . '-' . $kind . '.' . pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('kyc')->response($path, $name, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Put back on the market
     *
     * Undoes the sale: the listing shows on the website again (if active); the lead keeps its history.
     */
    public function revert($id)
    {
        $property = $this->findAccessible($id);
        abort_unless($property->isSold(), 404);
        abort_unless($this->isAdmin() || $this->viewer()->isApproved(), 403);

        $this->sales->revert($property);

        return response()->json(['success' => true]);
    }
}
