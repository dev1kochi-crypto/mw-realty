<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadNote;
use App\Models\LeadSource;
use App\Models\Property;
use Illuminate\Support\Collection;

/**
 * Everything the lead detail page (portal.crm.leads.show) shows, in one place: contacts,
 * the enquiry's captured details, the combined activity timeline and the source history.
 */
class LeadDetailService
{
    /** page_source values → what the team sees. */
    public const SOURCE_LABELS = [
        'property-detail' => 'Property enquiry form',
        'brochure-download' => 'Brochure download',
        'agent-profile-request' => 'Agent profile request',
        'agency-profile-request' => 'Agency profile request',
        'custom-request' => 'Custom property request',
        'manual' => 'Added in CRM',
        'import' => 'Excel import',
    ];

    private const EXTRA_FIELD_LABELS = [
        'property_category' => 'Property category',
        'specification' => 'Specification',
        'price_range' => 'Budget',
        'area' => 'Area',
        'preferred_location' => 'Preferred location',
        'move_in_timeline' => 'Move-in timeline',
        'furnishing_status' => 'Furnishing',
        'additional_details' => 'Additional details',
        'whatsapp_consent' => 'WhatsApp consent',
    ];

    private const ACTIVITY_STYLES = [
        'note' => ['icon' => 'fa-note-sticky', 'label' => 'Note', 'tone' => 'primary'],
        'enquiry' => ['icon' => 'fa-envelope-open-text', 'label' => 'Repeat enquiry', 'tone' => 'warning'],
        'stage' => ['icon' => 'fa-layer-group', 'label' => 'Stage', 'tone' => 'info'],
        'source' => ['icon' => 'fa-share-nodes', 'label' => 'Source', 'tone' => 'info'],
        'status' => ['icon' => 'fa-toggle-on', 'label' => 'Status', 'tone' => 'info'],
        'tags' => ['icon' => 'fa-tags', 'label' => 'Tags', 'tone' => 'info'],
        'details' => ['icon' => 'fa-pen', 'label' => 'Details edited', 'tone' => 'muted'],
        'assignment' => ['icon' => 'fa-user-check', 'label' => 'Assignment', 'tone' => 'success'],
        'created' => ['icon' => 'fa-inbox', 'label' => 'Lead received', 'tone' => 'accent'],
    ];

    public static function sourceLabel(?string $pageSource): string
    {
        return self::SOURCE_LABELS[$pageSource] ?? ($pageSource ? ucfirst(str_replace('-', ' ', $pageSource)) : 'Website');
    }

    /** Other emails / phones the person has used, beyond the primary ones. */
    public function alternateContacts(Lead $lead): array
    {
        return [
            'emails' => $lead->contacts->where('type', LeadContact::TYPE_EMAIL)
                ->reject(fn ($c) => $c->match_key === LeadContact::emailKey($lead->email))
                ->pluck('value')->values()->all(),
            'phones' => $lead->contacts->where('type', LeadContact::TYPE_PHONE)
                ->reject(fn ($c) => $c->match_key === LeadContact::phoneKey($lead->phone))
                ->map(fn ($c) => trim(($c->phone_country_code && !str_starts_with($c->value, '+') ? $c->phone_country_code . ' ' : '') . $c->value))
                ->values()->all(),
        ];
    }

    /**
     * Every email / phone the lead has, primary first — for the page's Email Addresses and Phone
     * Numbers cards. The primary is the one stored on the lead itself (leads.email / leads.phone).
     */
    public function contactList(Lead $lead): array
    {
        $build = function (string $type, ?string $primaryKey) use ($lead) {
            return $lead->contacts->where('type', $type)
                ->map(fn (LeadContact $c) => [
                    'id' => $c->id,
                    'value' => $c->value,
                    'label' => trim(($c->phone_country_code && !str_starts_with($c->value, '+') ? $c->phone_country_code . ' ' : '') . $c->value),
                    'primary' => $c->match_key === $primaryKey,
                    'added' => $c->created_at,
                ])
                ->sortByDesc('primary')->values()->all();
        };

        return [
            'emails' => $build(LeadContact::TYPE_EMAIL, LeadContact::emailKey($lead->email)),
            'phones' => $build(LeadContact::TYPE_PHONE, LeadContact::phoneKey($lead->phone)),
        ];
    }

    /** The form answers captured with the enquiry (budget, location, …), labelled, blanks dropped. */
    public function enquiryDetails(Lead $lead): array
    {
        $rows = [];
        foreach ((array) $lead->extra_fields as $key => $value) {
            if ($value === null || $value === '' || ($key === 'whatsapp_consent' && !$value)) {
                continue;
            }
            $rows[] = [
                'label' => self::EXTRA_FIELD_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                'value' => is_bool($value) ? ($value ? 'Yes' : 'No') : (is_array($value) ? implode(', ', $value) : (string) $value),
            ];
        }

        return $rows;
    }

    /**
     * Every enquiry this person sent, oldest first: the one that created the lead, then each
     * repeat enquiry merged into it — where it came from, which property, and what they wrote.
     */
    public function sourceHistory(Lead $lead): Collection
    {
        $enquiries = $lead->notesHistory->where('type', LeadNote::TYPE_ENQUIRY)->sortBy('id');
        $propertyIds = $enquiries->pluck('meta.property_id')->push($lead->property_id)->filter()->unique();
        $properties = Property::whereIn('id', $propertyIds)->get()->keyBy('id');
        $sources = LeadSource::whereIn('id', $enquiries->pluck('meta.source_id')->filter()->unique())->pluck('name', 'id');

        $first = [
            'at' => $lead->created_at,
            'channel' => self::sourceLabel($lead->page_source),
            'source' => $lead->source?->name,
            'property' => $properties->get($lead->property_id),
            'contact' => implode(' · ', array_filter([$lead->email, $lead->formatted_phone])),
            'message' => $lead->message,
            'page_url' => $lead->page_url,
            'first' => true,
        ];

        return collect([$first])->concat($enquiries->map(fn (LeadNote $note) => [
            'at' => $note->created_at,
            'channel' => self::sourceLabel($note->meta['page_source'] ?? null),
            'source' => isset($note->meta['source_id']) ? $sources->get($note->meta['source_id']) : null,
            'property' => isset($note->meta['property_id']) ? $properties->get($note->meta['property_id']) : null,
            'contact' => implode(' · ', array_filter([$note->meta['email'] ?? null, $note->meta['phone'] ?? null])),
            // Entries from before meta existed only have the summary text.
            'message' => $note->meta['message'] ?? $note->body,
            'page_url' => $note->meta['page_url'] ?? null,
            'first' => false,
        ]))->values();
    }

    /** Notes, system activity, assignment changes and the lead's arrival — newest first. */
    public function timeline(Lead $lead): Collection
    {
        $entries = $lead->notesHistory->map(fn (LeadNote $note) => $this->entry(
            $note->type,
            $note->created_at,
            $note->type === LeadNote::TYPE_ENQUIRY
                ? 'Enquired again via ' . self::sourceLabel($note->meta['page_source'] ?? null)
                : null,
            $note->type === LeadNote::TYPE_ENQUIRY ? ($note->meta['message'] ?? $note->body) : $note->body,
            $note->author_name,
            $note->id,
        ));

        foreach ($lead->assignmentHistory()->with(['agent:id,name', 'previousAgent:id,name'])->get() as $row) {
            $label = (new Lead(['assignment_type' => $row->assignment_type]))->assignmentLabel() ?? $row->assignment_type;
            $body = ($row->agent?->name ? 'Assigned to ' . $row->agent->name : 'Moved to agency level (unassigned)')
                . ' · ' . $label . ($row->note ? "\n" . $row->note : '');
            $entries->push($this->entry('assignment', $row->assigned_at, null, $body,
                $row->assigned_by_type === 'system' ? null : ucfirst($row->assigned_by_type), 0));
        }

        $entries->push($this->entry('created', $lead->created_at,
            'Lead received via ' . self::sourceLabel($lead->page_source),
            $lead->property ? 'About ' . $lead->property->getTranslation('title') : null, null, -1));

        return $entries->sortByDesc(fn ($e) => [$e['at']->getTimestamp(), $e['order']])->values();
    }

    private function entry(string $type, $at, ?string $title, ?string $body, ?string $author, int $order): array
    {
        $style = self::ACTIVITY_STYLES[$type] ?? ['icon' => 'fa-circle-info', 'label' => ucfirst($type), 'tone' => 'muted'];

        return $style + [
            'type' => $type,
            'at' => $at,
            'title' => $title ?? $style['label'],
            'body' => $body,
            'author' => $author,
            'order' => $order,
        ];
    }
}
