<?php

namespace App\Http\Requests\Crm;

use App\Services\Crm\OwnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Field-shape validation for manually creating a Lead. Ownership (which
 * tenant a stage_id/source_id/tags.* must belong to) is resolved here via
 * OwnerContext rather than duplicated — the same service the controller
 * trait (ScopesPortalOwner) and the Excel importer use.
 */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller scopes ownership before persistence.
        return true;
    }

    public function rules(): array
    {
        $ownerId = app(OwnerContext::class)->effectiveOwnerId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'message' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'owner_id' => ['nullable', 'integer', 'exists:portal_users,id'],
            'stage_id' => ['nullable', 'integer', Rule::exists('lead_stages', 'id')->where('portal_user_id', $ownerId)],
            'source_id' => ['nullable', 'integer', Rule::exists('lead_sources', 'id')->where('portal_user_id', $ownerId)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('lead_tags', 'id')->where('portal_user_id', $ownerId)],
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
