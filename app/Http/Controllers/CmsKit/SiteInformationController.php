<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\SiteInformation;
use App\Models\CmsKit\Language;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class SiteInformationController extends Controller
{
    protected array $translatableFields = [
        'company_name',
        'address',
        'country',
        'po_box',
        'fax',
        'working_hours',
        'privacy_policy',
        'terms_and_conditions',
        'disclaimer',
        'footer_description',
    ];

    protected function getValidationRules(bool $hasExistingRecord = false): array
    {
        $siteInfoConfig = config('cms-kit.database.site-information', []);
        $requiredFields = array_unique(array_merge(
            $siteInfoConfig['required'] ?? [],
            ['receipt_email', 'logo', 'favicon']
        ));
        $languages = Language::active()->get();
        $rules = [
            'extra_fields' => 'nullable|array',
            'translations' => 'nullable|array',
        ];

        foreach ([
            'phone_1', 'phone_2', 'phone_3', 'phone_4',
            'whatsapp_number', 'toll_free',
            'logo_alt', 'footer_logo_alt',
            'gtag'
        ] as $field) {
            if ($siteInfoConfig[$field] ?? true) {
                $prefix = in_array($field, $requiredFields) ? 'required' : 'nullable';
                $rules[$field] = $prefix . '|string|max:255';
            }
        }

        foreach (['email_1', 'email_2', 'email_3', 'email_4', 'receipt_email'] as $field) {
            if ($siteInfoConfig[$field] ?? true) {
                $prefix = in_array($field, $requiredFields) ? 'required' : 'nullable';
                $rules[$field] = [
                    $prefix,
                    'email:filter',
                    'max:255',
                    'regex:/^[^\\s@]+@([A-Za-z0-9-]+\\.)+[A-Za-z]{2,}$/',
                ];
            }
        }

        foreach (['facebook', 'twitter', 'linkedin', 'instagram', 'tiktok', 'snapchat', 'pinterest', 'youtube', 'skype', 'whatsapp_social', 'vimeo'] as $field) {
            if ($siteInfoConfig[$field] ?? true) {
                $prefix = in_array($field, $requiredFields) ? 'required' : 'nullable';
                $rules[$field] = $prefix . '|url|max:255';
            }
        }

        foreach (['custom_head_script', 'custom_body_script'] as $field) {
            if ($siteInfoConfig[$field] ?? true) {
                $rules[$field] = in_array($field, $requiredFields) ? 'required|string' : 'nullable|string';
            }
        }

        foreach (['logo' => 2048, 'favicon' => 1024, 'footer_logo' => 2048] as $field => $maxSize) {
            if ($siteInfoConfig[$field] ?? true) {
                $existingRecord = SiteInformation::first();
                $needsFile = in_array($field, $requiredFields)
                    && (
                        !$hasExistingRecord
                        || !$existingRecord?->{$field}
                        || request()->boolean("remove_{$field}")
                    );
                $rules[$field] = ($needsFile ? 'required' : 'nullable') . '|image|max:' . $maxSize;
                $rules["remove_{$field}"] = 'nullable|boolean';
            }
        }

        foreach ($languages as $lang) {
            foreach ($this->translatableFields as $field) {
                if ($siteInfoConfig[$field] ?? true) {
                    $prefix = in_array($field, $requiredFields) ? 'required' : 'nullable';
                    $rules["translations.{$lang->code}.{$field}"] = in_array($field, ['privacy_policy', 'terms_and_conditions', 'disclaimer']) ? $prefix . '|string' : $prefix . '|string|max:255';
                    if (in_array($field, ['address', 'footer_description'])) {
                        $rules["translations.{$lang->code}.{$field}"] = $prefix . '|string';
                    }
                }
            }
        }

        return $rules;
    }

    protected function getValidationAttributes(): array
    {
        return [
            'email_1' => 'email 1',
            'email_2' => 'email 2',
            'email_3' => 'email 3',
            'email_4' => 'email 4',
            'receipt_email' => 'recipient email',
            'logo' => 'main logo',
            'favicon' => 'favicon',
        ];
    }

    protected function mergeTranslatableExtraFields(array $translations): array
    {
        $fieldConfig = config('cms-kit.database.site-information.extra_fields', []);
        $translatableFields = collect($fieldConfig)->filter(fn ($field) => $field['translatable'] ?? false)->keys();

        foreach ($translations as $lang => $values) {
            $translations[$lang]['extra_fields'] = [];
            foreach ($translatableFields as $fieldName) {
                $translations[$lang]['extra_fields'][$fieldName] = data_get($values, "extra_fields.{$fieldName}");
            }
        }

        return $translations;
    }

    protected function getDefaultLanguageCode()
    {
        $defaultLanguage = Language::active()->where('is_default', true)->first();

        return $defaultLanguage?->code
            ?? Language::active()->orderByDesc('is_default')->value('code')
            ?? config('app.fallback_locale');
    }

    public function index()
    {
        $siteInfo = SiteInformation::first() ?? new SiteInformation();
        $languages = Language::active()->get();
        return view('cms-kit::site-information.index', compact('siteInfo', 'languages'));
    }

    public function update(Request $request)
    {
        $siteInfo = SiteInformation::first() ?? new SiteInformation();
        $defaultLanguageCode = $this->getDefaultLanguageCode();

        $data = $request->validate(
            $this->getValidationRules($siteInfo->exists),
            [],
            $this->getValidationAttributes()
        );

        // Handle File Uploads
        if ($request->hasFile('logo')) {
            if ($siteInfo->logo) {
                app(\App\Services\ManagedFiles::class)->delete($siteInfo->logo);
            }
            $data['logo'] = app(\App\Services\ManagedFiles::class)->store($request->file('logo'), 'site-info');
        } elseif ($request->boolean('remove_logo') && $siteInfo->logo) {
            app(\App\Services\ManagedFiles::class)->delete($siteInfo->logo);
            $data['logo'] = null;
            $data['logo_alt'] = null;
        }
        
        if ($request->hasFile('favicon')) {
            if ($siteInfo->favicon) {
                app(\App\Services\ManagedFiles::class)->delete($siteInfo->favicon);
            }
            $data['favicon'] = app(\App\Services\ManagedFiles::class)->store($request->file('favicon'), 'site-info');
        } elseif ($request->boolean('remove_favicon') && $siteInfo->favicon) {
            app(\App\Services\ManagedFiles::class)->delete($siteInfo->favicon);
            $data['favicon'] = null;
        }

        if ($request->hasFile('footer_logo')) {
            if ($siteInfo->footer_logo) {
                app(\App\Services\ManagedFiles::class)->delete($siteInfo->footer_logo);
            }
            $data['footer_logo'] = app(\App\Services\ManagedFiles::class)->store($request->file('footer_logo'), 'site-info');
        } elseif ($request->boolean('remove_footer_logo') && $siteInfo->footer_logo) {
            app(\App\Services\ManagedFiles::class)->delete($siteInfo->footer_logo);
            $data['footer_logo'] = null;
            $data['footer_logo_alt'] = null;
        }

        $extraFields = $siteInfo->extra_fields ?? [];
        foreach (config('cms-kit.database.site-information.extra_fields', []) as $key => $field) {
            if (($field['type'] ?? 'text') === 'file') {
                if ($request->hasFile("extra_fields.{$key}")) {
                    if (!empty($extraFields[$key])) {
                        app(\App\Services\ManagedFiles::class)->delete($extraFields[$key]);
                    }
                    $extraFields[$key] = app(\App\Services\ManagedFiles::class)->store($request->file("extra_fields.{$key}"), 'site-info');
                }
                // No new file submitted — keep whatever's already stored for this field.
            } else {
                $extraFields[$key] = $request->input("extra_fields.{$key}");
            }
        }
        $data['extra_fields'] = $extraFields;
        $translations = $this->mergeTranslatableExtraFields($request->input('translations', []));
        $data['translations'] = $translations;

        $siteInfoConfig = config('cms-kit.database.site-information', []);
        foreach ($this->translatableFields as $field) {
            // A field hidden via config never had a chance to be edited on this
            // submit — leave its stored value alone instead of nulling it out.
            if (!($siteInfoConfig[$field] ?? true)) {
                continue;
            }
            $data[$field] = data_get($translations, "{$defaultLanguageCode}.{$field}", $request->input($field));
        }

        $siteInfo->fill($data);
        $siteInfo->save();

        return redirect()->route('cms.site-information.index')->with('success', 'Site Information updated successfully.');
    }
}


