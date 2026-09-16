<?php

namespace App\Http\Requests;

use App\Models\CmsKit\Language;
use App\Models\Filter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyRequest extends FormRequest
{
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
            // Not user-supplied — the controller always auto-generates/preserves this (PROP001, ...);
            // the field is read-only in the form, so nothing enforces its presence here.
            'reference_no' => 'nullable|string|max:255',
            'rera_id' => 'nullable|string|max:255',
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
            'images' => 'nullable|array|max:30',
            'images.*' => 'required|image|max:4096',
            'amenities' => 'nullable|array|max:100',
            'amenities.*.label' => 'nullable|array',
            'amenities.*.label.*' => 'nullable|string|max:255',
            'amenities.*.icon' => 'nullable|image|max:1024',
            'amenities.*.existing_icon' => 'nullable|string',
            'easy_access' => 'nullable|array|max:100',
            'easy_access.*.label' => 'nullable|array',
            'easy_access.*.label.*' => 'nullable|string|max:255',
            'easy_access.*.icon' => 'nullable|image|max:1024',
            'easy_access.*.existing_icon' => 'nullable|string',
            'property_attributes' => 'nullable|array|max:100',
            'property_attributes.*.label' => 'nullable|array',
            'property_attributes.*.label.*' => 'nullable|string|max:255',
            'property_attributes.*.icon' => 'nullable|image|max:1024',
            'property_attributes.*.existing_icon' => 'nullable|string',
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
        $requiredSelectKeys = ['property_type', 'listing_type'];
        foreach (Filter::SELECT_KEYS as $key) {
            $filterId = Filter::where('key', $key)->where('status', true)->value('id');
            $presence = in_array($key, $requiredSelectKeys, true) ? 'required' : 'nullable';
            $rules[$key] = [$presence, 'string', 'max:255', Rule::exists('filter_values', 'value')->where('filter_id', $filterId ?? 0)->where('status', true)];
        }
        return $rules;
    }
}
