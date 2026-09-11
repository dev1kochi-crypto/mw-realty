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
            'translations.*' => 'required|array:title,description,address,city,country',
            'translations.*.title' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string|max:100000',
            'translations.*.address' => 'nullable|string|max:1000',
            'translations.*.city' => 'nullable|string|max:255',
            'translations.*.country' => 'nullable|string|max:255',
            'slug' => ['nullable', 'alpha_dash', 'max:255', Rule::unique('properties', 'slug')->ignore($this->route('id'))],
            'reference_no' => 'nullable|string|max:255',
            'bedrooms' => 'nullable|integer|min:0|max:1000',
            'bathrooms' => 'nullable|integer|min:0|max:1000',
            'sqft' => 'nullable|integer|min:0|max:4294967295',
            'price' => 'nullable|numeric|min:0|max:9999999999999.99',
            'currency' => 'sometimes|required|string|size:3|alpha',
            'image' => 'nullable|image|max:4096',
            'image_alt' => 'nullable|string|max:255',
            'images' => 'nullable|array|max:30',
            'images.*' => 'required|image|max:4096',
            'amenities' => 'nullable|array|max:100',
            'amenities.*' => 'nullable|string|max:255',
            'year_built' => 'nullable|integer|min:1800|max:2200',
            'floor' => 'nullable|string|max:255',
            'parking' => 'nullable|integer|min:0|max:10000',
            'view' => 'nullable|string|max:255',
        ];
        foreach (Language::active()->pluck('code') as $locale) $rules["translations.{$locale}.title"] = 'required|string|max:255';
        foreach (Filter::SELECT_KEYS as $key) {
            $filterId = Filter::where('key', $key)->where('status', true)->value('id');
            $rules[$key] = ['nullable', 'string', 'max:255', Rule::exists('filter_values', 'value')->where('filter_id', $filterId ?? 0)->where('status', true)];
        }
        return $rules;
    }
}
