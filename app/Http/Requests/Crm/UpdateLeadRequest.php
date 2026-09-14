<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Services\Crm\OwnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Field-shape validation for editing a Lead (both its core details and its
 * operational status/stage/source/tags/notes). Scoped foreign-key rules
 * accept a stage/source/tag belonging to *either* the lead's own owner or
 * the viewer's own effective owner — the former so a value untouched by the
 * form (e.g. Super Admin editing another owner's lead without changing its
 * Stage) still round-trips, the latter so Super Admin can actually assign
 * one of their own Master > Stage/Source/Tag entries to that lead, which is
 * what the Edit form's dropdowns now offer (see LeadController::show()). The
 * actual "is this lead yours" authorization still happens independently in
 * the controller via findOwned()/forOwner() — this lookup only shapes which
 * stage/source/tags are valid, it isn't itself the access-control boundary.
 */
class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller scopes ownership before persistence.
        return true;
    }

    public function rules(): array
    {
        $leadOwnerId = Lead::withTrashed()->find($this->route('id'))?->portal_user_id;
        $actorOwnerId = app(OwnerContext::class)->effectiveOwnerId();

        $ownedByLeadOrActor = function ($query) use ($leadOwnerId, $actorOwnerId) {
            $query->where(function ($q) use ($leadOwnerId, $actorOwnerId) {
                $q->where('portal_user_id', $leadOwnerId);
                if ($actorOwnerId && $actorOwnerId !== $leadOwnerId) {
                    $q->orWhere('portal_user_id', $actorOwnerId);
                }
            });
        };

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'message' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'owner_id' => ['nullable', 'integer', 'exists:portal_users,id'],
            'stage_id' => ['nullable', 'integer', Rule::exists('lead_stages', 'id')->where($ownedByLeadOrActor)],
            'source_id' => ['nullable', 'integer', Rule::exists('lead_sources', 'id')->where($ownedByLeadOrActor)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('lead_tags', 'id')->where($ownedByLeadOrActor)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->filled('email') && !$this->filled('phone')) {
                $validator->errors()->add('email', 'Either email or phone is required.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'stage_id.exists' => 'That stage does not belong to your account.',
            'source_id.exists' => 'That source does not belong to your account.',
            'tags.*.exists' => 'One of the selected tags does not belong to your account.',
        ];
    }
}
