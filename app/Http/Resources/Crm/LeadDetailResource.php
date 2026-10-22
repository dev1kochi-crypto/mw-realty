<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The lead detail screen. Built from Portal\Crm\LeadController::detailPageData() — the same data
 * the portal's lead page renders — so the two can't drift apart. Dates are ISO 8601.
 */
class LeadDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $page = $this->resource;
        $lead = $page['lead'];
        $item = fn ($row) => $row ? ['id' => $row->id, 'name' => $row->name, 'color' => $row->color ?? null] : null;
        $property = fn ($p) => $p ? ['id' => $p->id, 'title' => $p->getTranslation('title'), 'reference_no' => $p->reference_no] : null;

        return [
            'lead' => array_merge((new LeadResource($lead))->toArray($request), [
                // "Came in via" — the channel the lead arrived through (AI chat, form, import…).
                'channel' => \App\Services\Crm\LeadDetailService::sourceLabel($lead->page_source),
                'property' => $lead->property ? [
                    'id' => $lead->property->id,
                    'title' => $lead->property->getTranslation('title'),
                    'reference_no' => $lead->property->reference_no,
                    'url' => $lead->property->slug ? url('/property-details/' . $lead->property->slug) : null,
                ] : null,
            ]),
            'previous_lead_id' => $page['previousLeadId'],
            'next_lead_id' => $page['nextLeadId'],
            'contacts' => [
                'emails' => collect($page['contacts']['emails'])->map(fn ($c) => ['id' => $c['id'], 'value' => $c['value'], 'label' => $c['label'], 'primary' => $c['primary']])->values(),
                'phones' => collect($page['contacts']['phones'])->map(fn ($c) => ['id' => $c['id'], 'value' => $c['value'], 'label' => $c['label'], 'primary' => $c['primary']])->values(),
            ],
            'enquiry_details' => $page['enquiryDetails'],
            'timeline' => $page['timeline']->map(fn ($e) => [
                'type' => $e['type'],
                'label' => $e['label'],
                'icon' => $e['icon'],
                'tone' => $e['tone'],
                'title' => $e['title'],
                'body' => $e['body'],
                'author' => $e['author'],
                'at' => $e['at']?->toIso8601String(),
            ])->values(),
            'source_history' => $page['sourceHistory']->map(fn ($s) => [
                'at' => $s['at']?->toIso8601String(),
                'channel' => $s['channel'],
                'source' => $s['source'],
                'property' => $property($s['property']),
                'contact' => $s['contact'],
                'message' => $s['message'],
                'page_url' => $s['page_url'],
                'first' => $s['first'],
            ])->values(),
            'purchases' => $page['purchases']->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->getTranslation('title'),
                'reference_no' => $p->reference_no,
                'segment' => $p->segment,
                'sold_type' => $p->sold_type,
                'sold_price' => $p->sold_price,
                'currency' => $p->currency,
                'sold_at' => $p->sold_at?->toIso8601String(),
                'rented_until' => $p->rented_until?->toIso8601String(),
                'url' => route(($p->segment === \App\Models\Property::SEGMENT_COMMERCIAL ? 'portal.commercial' : 'portal.properties') . '.show', $p->id),
            ])->values(),
            'notes' => $page['notes']->map(fn ($note) => [
                'id' => $note->id,
                'body' => $note->body,
                'author_name' => $note->author_name,
                'created_at' => $note->created_at?->toIso8601String(),
            ])->values(),
            // Website activity: GET /leads/{id}/insights/summary (+ /insights for the next pages).
            'has_insights' => $page['hasInsights'],
            'assignment' => [
                'can_assign' => $page['canAssign'],
                'agent_options' => $page['agentOptions']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values(),
                'history' => $page['assignmentHistory']->map(fn ($row) => [
                    'assigned_at' => $row->assigned_at?->toIso8601String(),
                    'agent' => $row->agent ? ['id' => $row->agent->id, 'name' => $row->agent->name] : null,
                    'note' => $row->note,
                    'by' => $row->assigned_by_type === 'system' ? null : $row->assigned_by_type,
                ])->values(),
            ],
            // What the stage / source / tag pickers offer — the viewer's master data plus the lead's current values.
            'options' => [
                'stages' => $page['stages']->map($item)->values(),
                'sources' => $page['sources']->map($item)->values(),
                'tags' => $page['tags']->map($item)->values(),
            ],
            'permissions' => [
                'can_delete' => $page['canDelete'],
                'can_transfer' => $page['isAdmin'],
            ],
        ];
    }
}
