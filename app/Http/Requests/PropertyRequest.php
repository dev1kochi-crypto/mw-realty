<?php

namespace App\Http\Requests;

use App\Models\CmsKit\Language;
use App\Models\Filter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyRequest extends FormRequest
{
    /** Brochure / floor-plan file types: PDF, images and office documents. */
    public const BROCHURE_EXTENSIONS = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,ppt,pptx';

    public function authorize(): bool { return true; } // The controller scopes ownership before persistence.

    public function rules(): array
    {
        $rules = [
            'translations' => 'required|array|min:1|max:30',
            'translations.*' => 'required|array:title,key_features,description,address,community,city,country',
            'translations.*.title' => 'required|string|max:255',
            'translations.*.key_features' => 'nullable|string|max:5000',
            'translations.*.description' => 'nullable|string|max:100000',
            'translations.*.address' => 'required|string|max:1000',
            'translations.*.community' => 'nullable|string|max:255',
            'translations.*.city' => 'required|string|max:255',
            'translations.*.country' => 'required|string|max:255',
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('properties', 'slug')->ignore($this->route('id'))],
            // Displayed read-only as a preview; the controller accepts only an unused RERA-prefixed value.
            'reference_no' => 'nullable|string|max:255',
            'rera_id' => 'nullable|string|max:255',
            // DLD compliance (Compliance tab). Optional to save a draft; ListingComplianceService decides
            // whether the listing is complete enough to be reviewed. One DLD permit = one listing.
            'permit_number' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('properties', 'permit_number')->ignore($this->route('id'))],
            'permit_expires_at' => 'nullable|date_format:Y-m-d',
            'permit_qr' => 'nullable|image|max:2048',
            'permit_verification_url' => 'nullable|url:https,http|max:2048',
            'postal_code' => 'nullable|string|max:50',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'bedrooms' => 'nullable|integer|min:0|max:1000',
            'bathrooms' => 'nullable|integer|min:0|max:1000',
            'sqft' => 'nullable|integer|min:0|max:4294967295',
            'price' => 'required|numeric|min:0|max:9999999999999.99',
            'currency' => 'sometimes|required|string|size:3|alpha',
            'image' => 'nullable|image|max:4096',
            'image_alt' => 'nullable|string|max:255',
            'brochure' => 'nullable|file|extensions:' . self::BROCHURE_EXTENSIONS . '|max:20480',
            'images' => 'nullable|array|max:30',
            'images.*' => 'required|image|max:4096',
            // Amenities / Easy Access / Attributes: option codes from Master › Property Options (see the loop below).
            'amenities' => 'nullable|array|max:200',
            'easy_access' => 'nullable|array|max:200',
            'property_attributes' => 'nullable|array|max:200',
            'rental_period' => 'nullable|string|max:50',
            // Permit flow (App\Support\PermitRules / App\Services\Permits\PermitVerifier).
            'permit_type' => ['nullable', Rule::in(\App\Support\PermitRules::DUBAI_TYPES)],
            'permit_city' => ['nullable', Rule::in(array_keys(\App\Support\PermitRules::NORTHERN_CITIES))],
            'permit_token' => 'nullable|string|max:64',
            'availability' => 'nullable|in:immediately,from_date',
            'available_dates' => 'nullable|array|max:60|required_if:availability,from_date',
            'available_dates.*' => 'date_format:Y-m-d',
            'developer' => 'nullable|string|max:255',
            'unit_number' => 'nullable|string|max:100',
            'owner_name' => 'nullable|string|max:255',
            'upgraded' => 'nullable|boolean',
            'video_tour_url' => 'nullable|url|max:2048',
            'cheques' => 'nullable|integer|min:1|max:12',
            'year_built' => 'nullable|integer|min:1800|max:2200',
            'floor' => 'nullable|string|max:255',
            'parking' => 'nullable|integer|min:0|max:10000',
            'garage' => 'nullable|integer|min:0|max:1000',
            'direct_from_owner' => 'nullable|string|max:255',
            'security_deposit' => 'nullable|numeric|min:0|max:9999999999999.99',
            'virtual_tour_url' => 'nullable|url|max:2048',
            'view' => 'nullable|string|max:255',
            'floor_plans' => 'nullable|array|max:50',
            'floor_plans.*.label' => 'nullable|string|max:255',
            'floor_plans.*.size_from' => 'nullable|integer|min:0',
            'floor_plans.*.size_to' => 'nullable|integer|min:0',
            'floor_plans.*.image' => 'nullable|image|max:4096',
            'floor_plans.*.existing_image' => 'nullable|string',
            'floor_plan_file' => 'nullable|file|max:10240',
            'nearby_places' => 'nullable|array|max:100',
            'nearby_places.*' => 'integer|exists:nearby_places,id',
            'published_at' => 'nullable|date',
            'order_index' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
            'metadata.meta_title' => 'nullable|string|max:255',
            'metadata.meta_description' => 'nullable|string|max:500',
            'metadata.meta_keywords' => 'nullable|string|max:500',
            'metadata.canonical_url' => 'nullable|url|max:2048',
            'metadata.og_title' => 'nullable|string|max:255',
            'metadata.og_description' => 'nullable|string|max:500',
            'metadata.other_meta_tags' => 'nullable|string',
            'metadata_og_image' => 'nullable|image|max:4096',
        ];
        foreach (Language::active()->pluck('code') as $locale) $rules["translations.{$locale}.title"] = 'required|string|max:255';
        $requiredSelectKeys = ['property_type', 'listing_type', 'category', 'location'];
        foreach (Filter::SELECT_KEYS as $key) {
            $filterId = Filter::where('key', $key)->where('status', true)->value('id');
            $presence = in_array($key, $requiredSelectKeys, true) ? 'required' : 'nullable';
            $rules[$key] = [$presence, 'string', 'max:255', Rule::exists('filter_values', 'value')->where('filter_id', $filterId ?? 0)->where('status', true)];
        }
        // Form-only dropdowns and checkbox lists: any option of the list (an option switched off later
        // stays valid on listings that already have it).
        foreach ([Filter::EMIRATE_KEY => 'emirate', Filter::RENTAL_PERIOD_KEY => 'rental_period'] + Filter::ICON_LISTS as $key => $field) {
            $filterId = Filter::where('key', $key)->value('id');
            $exists = Rule::exists('filter_values', 'value')->where('filter_id', $filterId ?? 0);
            // Emirate decides which permit the listing needs (PermitRules), so it can't be left empty.
            $presence = $key === Filter::EMIRATE_KEY ? 'required' : 'nullable';
            $rules[isset(Filter::ICON_LISTS[$key]) ? "{$field}.*" : $field] = [$presence, 'string', 'max:100', $exists];
        }
        return $rules;
    }

    public function messages(): array
    {
        return [
            'permit_number.unique' => 'This DLD permit number is already used by another listing. Each advertising permit covers one listing.',
            'permit_number.regex' => 'The permit number may only contain letters, numbers, dashes and slashes.',
            'available_dates.required_if' => 'Pick at least one available date, or choose "Immediately".',
            'emirate.required' => 'Choose the emirate — it decides which advertising permit the listing needs.',
        ];
    }
}
