<?php

namespace App\Services;

use App\Models\MarketingProperty;
use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin's Marketing Properties list — the one place it is read and changed.
 *  - Portal › Listings › Marketing Properties picks agencies / agents, adds their listings and
 *    orders them (Crm\Marketing\MarketingPropertyController).
 *  - Home "Realty Property" shows the first HOME_LIMIT (HomePageService::realtyProperty).
 *  - /marketing-properties shows all of them, in the same order (PropertiesPageService).
 * order_index is 1-based and gap-free; new listings go to the top.
 */
class MarketingPropertyService
{
    public const HOME_LIMIT = 12;
    private const PAGE_SIZE = 20;

    /** Agencies + agents to pick from — searched and paged on the server. */
    public function accounts(string $search, int $page): array
    {
        $paginator = PortalUser::query()
            ->whereIn('type', ['company', 'agent'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withCount(['properties as listings_count' => fn ($q) => $q->where('status', true)])
            ->orderByRaw("type = 'company' desc")
            ->orderBy('company_name')->orderBy('name')
            ->paginate(self::PAGE_SIZE, ['id', 'name', 'company_name', 'type', 'company_id'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (PortalUser $u) => [
                'id' => $u->id,
                'name' => $u->displayName(),
                'type' => $u->type === 'company' ? 'Agency' : 'Agent',
                'listings' => (int) $u->listings_count,
            ])->values(),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
        ];
    }

    /**
     * Active listings of the chosen accounts — an agency's own listings, an agent's own listings
     * and the ones they're the listing agent on — with whether each is already on the list.
     */
    public function propertiesOf(array $accountIds, string $search, int $page, string $lang): array
    {
        $paginator = Property::query()
            ->where('status', true)
            ->where(fn ($q) => $q->whereIn('portal_user_id', $accountIds)->orWhereIn('agent_id', $accountIds))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference_no', 'like', "%{$search}%")
                ->orWhere('translations', 'like', "%{$search}%")))
            ->with(['owner:id,name,company_name,type', 'marketingEntry:id,property_id'])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(self::PAGE_SIZE, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (Property $p) => $this->row($p, $lang) + [
                'in_list' => (bool) $p->marketingEntry,
            ])->values(),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
            'total' => $paginator->total(),
        ];
    }

    /** The whole list in order — for the admin screen (it's hand-curated, so it stays small). */
    public function list(string $lang): Collection
    {
        return Property::query()->marketing()->marketingOrder()
            ->with('owner:id,name,company_name,type')
            ->get()
            ->map(fn (Property $p) => $this->row($p, $lang));
    }

    /** Add listings to the top of the list, in the order given; ones already on it are skipped. */
    public function add(array $propertyIds): int
    {
        return DB::transaction(function () use ($propertyIds) {
            $existing = MarketingProperty::whereIn('property_id', $propertyIds)->pluck('property_id')->all();
            $new = Property::whereIn('id', array_diff($propertyIds, $existing))->where('status', true)->pluck('id')
                ->sortBy(fn ($id) => array_search($id, $propertyIds))->values();
            if ($new->isEmpty()) {
                return 0;
            }

            MarketingProperty::query()->increment('order_index', $new->count());
            $new->each(fn ($id, $i) => MarketingProperty::create(['property_id' => $id, 'order_index' => $i + 1]));

            return $new->count();
        });
    }

    public function remove(int $propertyId): void
    {
        DB::transaction(function () use ($propertyId) {
            MarketingProperty::where('property_id', $propertyId)->delete();
            $this->renumber();
        });
    }

    /** Save a new order: $propertyIds is the full list, first = top. Unknown ids are ignored. */
    public function reorder(array $propertyIds): void
    {
        DB::transaction(function () use ($propertyIds) {
            $position = array_flip(array_values($propertyIds));
            MarketingProperty::lockForUpdate()->get()
                ->sortBy(fn ($row) => [$position[$row->property_id] ?? PHP_INT_MAX, $row->order_index])
                ->values()
                ->each(fn ($row, $i) => $row->order_index !== $i + 1 ? $row->update(['order_index' => $i + 1]) : null);
        });
    }

    /** Home "Realty Property" — the first HOME_LIMIT still-active listings of the list. */
    public function homeProperties(): Collection
    {
        return Property::where('status', true)->marketing()->marketingOrder()->take(self::HOME_LIMIT)->get();
    }

    public function hasAny(): bool
    {
        return MarketingProperty::whereHas('property', fn ($q) => $q->where('status', true))->exists();
    }

    private function renumber(): void
    {
        MarketingProperty::orderBy('order_index')->orderBy('id')->get()
            ->each(fn ($row, $i) => $row->order_index !== $i + 1 ? $row->update(['order_index' => $i + 1]) : null);
    }

    private function row(Property $p, string $lang): array
    {
        return [
            'id' => $p->id,
            'title' => $p->getTranslation('title', $lang) ?: $p->reference_no,
            'reference' => $p->reference_no,
            'owner' => $p->owner?->displayName(),
            'price' => $p->price ? ($p->currency ?? 'AED') . ' ' . number_format((float) $p->price) : null,
            // First gallery photo, same source as the website cards (MapsPropertyCards).
            'image' => $p->galleryImages()[0]['url'] ?? null,
            'active' => (bool) $p->status,
        ];
    }
}
