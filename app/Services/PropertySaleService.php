<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Property;
use App\Services\Crm\LeadNoteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a listing sold / rented and ties it to the buyer's lead: an existing lead of the listing's
 * owner, or a new one created from the buyer details given. The lead moves to the owner's won stage
 * (closed_at = the sale date, so the Sales report counts it then) and gets a "sale" timeline entry.
 * A sold listing is unpublished, so it drops off the website (home, listings, map, sitemap).
 */
class PropertySaleService
{
    public function __construct(private readonly LeadNoteService $notes)
    {
    }

    /** Leads a sale may be linked to — every lead the viewer sees in their CRM (all leads for Super Admin, null viewer id). */
    public function buyerLeads(?int $viewerId)
    {
        return Lead::forOwner($viewerId);
    }

    /**
     * @param array{type:string, price:float|string, sold_at:string, rented_until?:?string, commission?:float|string|null,
     *              notes?:?string, lead_id?:?int, buyer?:array{name:string, email?:?string, phone?:?string, phone_country_code?:?string}} $data
     */
    public function markSold(Property $property, array $data, ?int $viewerId = null): Property
    {
        if ($property->isSold()) {
            throw ValidationException::withMessages(['property' => 'This listing is already marked ' . $property->sold_type . '.']);
        }

        return DB::transaction(function () use ($property, $data, $viewerId) {
            $soldAt = Carbon::parse($data['sold_at'])->setTimeFrom(now());
            $lead = !empty($data['lead_id'])
                ? $this->buyerLeads($viewerId)->findOrFail($data['lead_id'])
                : $this->createBuyerLead($property, $data['buyer']);

            $property->fill([
                'sold_at' => $soldAt,
                'sold_type' => $data['type'],
                'sold_price' => $data['price'],
                'sold_commission' => $data['commission'] ?? null,
                'rented_until' => $data['type'] === Property::RENTED ? ($data['rented_until'] ?? null) : null,
                'sold_lead_id' => $lead->id,
                'sold_agent_id' => $lead->agent_id ?? $property->agent_id,
                'sold_notes' => $data['notes'] ?? null,
                'status_before_sold' => $property->status,
                // Off the website, and no longer using a premium slot.
                'status' => false,
                'featured' => false,
                'featured_from' => null,
                'featured_until' => null,
            ])->save();

            $this->closeLeadAsWon($lead, $property, $soldAt);

            return $property;
        });
    }

    /** "Revert to available": back on the market as it was before; the lead keeps its history. */
    public function revert(Property $property): Property
    {
        return DB::transaction(function () use ($property) {
            $lead = $property->soldLead;
            $type = $property->sold_type;

            $property->fill([
                'status' => $property->status_before_sold ?? true,
                'sold_at' => null, 'sold_type' => null, 'sold_price' => null, 'sold_commission' => null, 'rented_until' => null,
                'sold_lead_id' => null, 'sold_agent_id' => null, 'sold_notes' => null, 'status_before_sold' => null,
            ])->save();

            if ($lead) {
                $this->notes->log($lead, 'sale', ucfirst((string) $type) . ' reverted: ' . $this->propertyLabel($property) . ' is available again.', [
                    'property_id' => $property->id, 'reverted' => true,
                ]);
            }

            return $property;
        });
    }

    private function createBuyerLead(Property $property, array $buyer): Lead
    {
        return Lead::create([
            'property_id' => $property->id,
            'portal_user_id' => $property->portal_user_id,
            'agent_id' => $property->agent_id,
            'assignment_type' => $property->agent_id ? Lead::ASSIGN_PROPERTY_AGENT : null,
            'assigned_at' => $property->agent_id ? now() : null,
            'name' => $buyer['name'],
            'email' => $buyer['email'] ?? null,
            'phone' => $buyer['phone'] ?? null,
            'phone_country_code' => $buyer['phone_country_code'] ?? null,
            'page_source' => 'Marked sold',
            'status' => 'active',
            'message' => 'Added when the listing was marked sold / rented.',
            'last_enquired_at' => now(),
        ]);
    }

    private function closeLeadAsWon(Lead $lead, Property $property, Carbon $soldAt): void
    {
        $stage = $this->wonStage($lead->portal_user_id);
        if ($stage && $lead->stage_id !== $stage->id) {
            $lead->stage_id = $stage->id;
            $lead->save();
        }
        // Counted in the Sales report on the sale date, not the day it was recorded.
        $lead->forceFill(['closed_at' => $soldAt])->saveQuietly();

        $verb = $property->sold_type === Property::RENTED ? 'Rented' : 'Bought';
        $body = $verb . ' ' . $this->propertyLabel($property) . ' for ' . $property->currency . ' ' . number_format((float) $property->sold_price)
            . ' on ' . $soldAt->format('d M Y')
            . ($property->rented_until ? ' (lease until ' . $property->rented_until->format('d M Y') . ')' : '')
            . ($property->sold_notes ? "\n" . $property->sold_notes : '');

        $this->notes->log($lead, 'sale', $body, [
            'property_id' => $property->id,
            'type' => $property->sold_type,
            'price' => (float) $property->sold_price,
            'date' => $soldAt->toDateString(),
        ]);
    }

    /** The lead owner's first won stage (closed, not "lost") — created if that owner has none. */
    private function wonStage(?int $ownerId): ?LeadStage
    {
        $stage = LeadStage::query()
            ->when($ownerId, fn ($q, $id) => $q->where('portal_user_id', $id), fn ($q) => $q->whereNull('portal_user_id'))
            ->where('is_closed', true)->orderBy('order_index')->get()
            ->first(fn (LeadStage $s) => $s->isWon());

        if ($stage || !$ownerId) {
            return $stage;
        }

        return LeadStage::create([
            'portal_user_id' => $ownerId,
            'name' => 'Closed Won',
            'color' => '#14b8a6',
            'is_closed' => true,
            'order_index' => (int) LeadStage::where('portal_user_id', $ownerId)->max('order_index') + 1,
        ]);
    }

    private function propertyLabel(Property $property): string
    {
        return ($property->getTranslation('title') ?: 'Property') . ($property->reference_no ? ' (' . $property->reference_no . ')' : '');
    }
}
