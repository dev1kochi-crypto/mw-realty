<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the CRM Leads listing — every field a column can show (LeadTablePreferenceService);
 * relations come eager-loaded from LeadService::filteredQuery(). `insights` only when the
 * listing loaded the website-visitor counts (an insight column shown / sorted).
 */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasInsights = array_key_exists('visitor_seconds', $this->resource->getAttributes());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_country_code' => $this->phone_country_code,
            'formatted_phone' => $this->formatted_phone,
            'company' => $this->company,
            'country' => $this->country,
            'message' => $this->message,
            'status' => $this->status,
            'stage' => $this->stage ? ['id' => $this->stage->id, 'name' => $this->stage->name, 'color' => $this->stage->color] : null,
            'source' => $this->source ? ['id' => $this->source->id, 'name' => $this->source->name] : null,
            'tags' => $this->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])->values(),
            'owner' => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->displayName(), 'is_agency' => $this->owner->isAgency()] : null,
            'agent' => $this->agent ? ['id' => $this->agent->id, 'name' => $this->agent->name] : null,
            'assignment_label' => $this->assignmentLabel(),
            'property' => $this->property ? ['id' => $this->property->id, 'title' => $this->property->getTranslation('title')] : null,
            'campaign' => $this->extra_fields['facebook_campaign'] ?? null,
            'page_url' => $this->page_url,
            'page_source' => $this->page_source,
            'enquiry_count' => max(1, (int) $this->enquiry_count),
            'notes_count' => (int) ($this->notes_history_count ?? 0),
            'has_insights' => $this->visitor_lead_id !== null,
            'insights' => $hasInsights ? [
                'property_views' => (int) $this->visitor_property_views,
                'seconds' => (int) $this->visitor_seconds,
                'searches' => (int) $this->visitor_searches,
                'chats' => (int) $this->visitor_chats,
                'last_seen' => $this->visitor_last_seen ? \Illuminate\Support\Carbon::parse($this->visitor_last_seen)->toIso8601String() : null,
            ] : null,
            'last_enquired_at' => $this->last_enquired_at?->toIso8601String(),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when($this->trashed(), fn () => $this->deleted_at?->toIso8601String()),
        ];
    }
}
