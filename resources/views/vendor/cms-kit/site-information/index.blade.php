@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Site Information</li>
@endsection

@section('content')
<div class="container-fluid">
    @php
        $siteInfoConfig = config('cms-kit.database.site-information', []);
        $siteInfoRequired = $siteInfoConfig['required'] ?? [];
        $showLanguageUi = config('cms-kit.common.modules.languages', true);
        $siteInfoExtraFields = config('cms-kit.database.site-information.extra_fields', []);
        $hasTranslatableExtraFields = collect($siteInfoExtraFields)->contains(fn($field) => $field['translatable'] ?? false);
        $translations = $siteInfo->translations ?? [];
        $socialFields = [
            'facebook' => 'Facebook',
            'twitter' => 'Twitter',
            'linkedin' => 'Linkedin',
            'instagram' => 'Instagram',
            'tiktok' => 'Tiktok',
            'snapchat' => 'Snapchat',
            'pinterest' => 'Pinterest',
            'youtube' => 'Youtube',
            'skype' => 'Skype',
            'whatsapp_social' => 'Whatsapp',
            'vimeo' => 'Vimeo',
        ];
    @endphp

    <div class="alert alert-light border-start border-primary border-4 py-3 mb-4 shadow-sm">
        <div class="d-flex align-items-center">
            <i class="fas fa-info-circle text-primary fs-4 me-3"></i>
            <div>
                <h6 class="mb-1 fw-bold text-primary">Site Configuration Note</h6>
                <p class="mb-0 text-muted small">
                    @if($showLanguageUi)
                        Use language tabs for company details and legal content that should vary by language. Shared assets, contact channels, and tracking remain global.
                    @else
                        Manage company details, legal content, shared assets, contact channels, and tracking settings here.
                    @endif
                </p>
            </div>
        </div>
    </div>

    <form action="{{ route('cms.site-information.update') }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-primary">{{ $showLanguageUi ? 'Company & Legal Content by Language' : 'Company & Legal Content' }}</h5>
                    </div>
                    <div class="card-body">
                        @if($showLanguageUi)
                        <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="siteInfoTabs" role="tablist">
                            @foreach($languages as $lang)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="site-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#site-panel-{{ $lang->code }}" type="button" role="tab">
                                    <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                                </button>
                            </li>
                            @endforeach
                        </ul>
                        @endif

                        <div class="tab-content language-switcher-content" id="siteInfoTabsContent">
                            @foreach($languages as $lang)
                            @php $trans = $translations[$lang->code] ?? []; @endphp
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="site-panel-{{ $lang->code }}" role="tabpanel">
                                <div class="row">
                                    @if($siteInfoConfig['company_name'] ?? true)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Company Name {!! in_array('company_name', $siteInfoRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                        <input type="text" name="translations[{{ $lang->code }}][company_name]" class="form-control @error("translations.{$lang->code}.company_name") is-invalid @enderror" value="{{ old("translations.{$lang->code}.company_name", $trans['company_name'] ?? $siteInfo->company_name) }}" {{ in_array('company_name', $siteInfoRequired) ? 'required' : '' }}>
                                        @error("translations.{$lang->code}.company_name")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @endif
                                    @if($siteInfoConfig['country'] ?? true)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Country</label>
                                        <input type="text" name="translations[{{ $lang->code }}][country]" class="form-control @error("translations.{$lang->code}.country") is-invalid @enderror" value="{{ old("translations.{$lang->code}.country", $trans['country'] ?? $siteInfo->country) }}">
                                        @error("translations.{$lang->code}.country")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @endif
                                    @if($siteInfoConfig['po_box'] ?? true)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">P.O Box</label>
                                        <input type="text" name="translations[{{ $lang->code }}][po_box]" class="form-control @error("translations.{$lang->code}.po_box") is-invalid @enderror" value="{{ old("translations.{$lang->code}.po_box", $trans['po_box'] ?? $siteInfo->po_box) }}">
                                        @error("translations.{$lang->code}.po_box")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @endif
                                    @if($siteInfoConfig['fax'] ?? true)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Fax</label>
                                        <input type="text" name="translations[{{ $lang->code }}][fax]" class="form-control @error("translations.{$lang->code}.fax") is-invalid @enderror" value="{{ old("translations.{$lang->code}.fax", $trans['fax'] ?? $siteInfo->fax) }}">
                                        @error("translations.{$lang->code}.fax")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @endif
                                    @if($siteInfoConfig['address'] ?? true)
                                    <div class="col-12 mb-3">
                                        <label class="form-label fw-bold">Address {!! in_array('address', $siteInfoRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                        <textarea name="translations[{{ $lang->code }}][address]" class="form-control @error("translations.{$lang->code}.address") is-invalid @enderror" rows="3" {{ in_array('address', $siteInfoRequired) ? 'required' : '' }}>{{ old("translations.{$lang->code}.address", $trans['address'] ?? $siteInfo->address) }}</textarea>
                                        @error("translations.{$lang->code}.address")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @endif
                                    @if($siteInfoConfig['working_hours'] ?? true)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Working Hours</label>
                                        <input type="text" name="translations[{{ $lang->code }}][working_hours]" class="form-control @error("translations.{$lang->code}.working_hours") is-invalid @enderror" value="{{ old("translations.{$lang->code}.working_hours", $trans['working_hours'] ?? $siteInfo->working_hours) }}" placeholder="e.g. Mon - Fri: 9:00 AM - 6:00 PM">
                                        @error("translations.{$lang->code}.working_hours")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @endif
                                </div>

                                @if(($siteInfoConfig['privacy_policy'] ?? true) || ($siteInfoConfig['terms_and_conditions'] ?? true) || ($siteInfoConfig['disclaimer'] ?? true) || ($siteInfoConfig['footer_description'] ?? true) || config('cms-kit.database.site-information.extra_fields.security_settings') || config('cms-kit.database.site-information.extra_fields.cookie_policy'))
                                <hr class="my-4">

                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3 text-secondary">Legal Content</h6>
                                        @if($siteInfoConfig['privacy_policy'] ?? true)
                                        <div class="mb-3">
                                            <label class="form-label">Privacy Policy</label>
                                            <textarea name="translations[{{ $lang->code }}][privacy_policy]" class="form-control tinymce-site-info @error("translations.{$lang->code}.privacy_policy") is-invalid @enderror" rows="6">{{ old("translations.{$lang->code}.privacy_policy", $trans['privacy_policy'] ?? $siteInfo->privacy_policy) }}</textarea>
                                            @error("translations.{$lang->code}.privacy_policy")
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        @endif
                                        @if($siteInfoConfig['terms_and_conditions'] ?? true)
                                        <div class="mb-3">
                                            <label class="form-label">Terms & Conditions</label>
                                            <textarea name="translations[{{ $lang->code }}][terms_and_conditions]" class="form-control tinymce-site-info @error("translations.{$lang->code}.terms_and_conditions") is-invalid @enderror" rows="6">{{ old("translations.{$lang->code}.terms_and_conditions", $trans['terms_and_conditions'] ?? $siteInfo->terms_and_conditions) }}</textarea>
                                            @error("translations.{$lang->code}.terms_and_conditions")
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        @endif
                                        @if($siteInfoConfig['disclaimer'] ?? true)
                                        <div class="mb-3">
                                            <label class="form-label">Disclaimer</label>
                                            <textarea name="translations[{{ $lang->code }}][disclaimer]" class="form-control tinymce-site-info @error("translations.{$lang->code}.disclaimer") is-invalid @enderror" rows="6">{{ old("translations.{$lang->code}.disclaimer", $trans['disclaimer'] ?? $siteInfo->disclaimer) }}</textarea>
                                            @error("translations.{$lang->code}.disclaimer")
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        @endif
                                        @if(config('cms-kit.database.site-information.extra_fields.security_settings'))
                                        <div class="mb-3">
                                            <label class="form-label">Security Setting</label>
                                            <textarea name="translations[{{ $lang->code }}][extra_fields][security_settings]" class="form-control tinymce-site-info @error("translations.{$lang->code}.extra_fields.security_settings") is-invalid @enderror" rows="6">{{ old("translations.{$lang->code}.extra_fields.security_settings", $trans['extra_fields']['security_settings'] ?? '') }}</textarea>
                                            @error("translations.{$lang->code}.extra_fields.security_settings")
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        @endif
                                        @if(config('cms-kit.database.site-information.extra_fields.cookie_policy'))
                                        <div class="mb-3">
                                            <label class="form-label">Cookie Policy</label>
                                            <textarea name="translations[{{ $lang->code }}][extra_fields][cookie_policy]" class="form-control tinymce-site-info @error("translations.{$lang->code}.extra_fields.cookie_policy") is-invalid @enderror" rows="6">{{ old("translations.{$lang->code}.extra_fields.cookie_policy", $trans['extra_fields']['cookie_policy'] ?? '') }}</textarea>
                                            @error("translations.{$lang->code}.extra_fields.cookie_policy")
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        @endif
                                        @if($siteInfoConfig['footer_description'] ?? true)
                                        <div class="mb-0">
                                            <label class="form-label">Footer Description</label>
                                            <textarea name="translations[{{ $lang->code }}][footer_description]" class="form-control @error("translations.{$lang->code}.footer_description") is-invalid @enderror" rows="3">{{ old("translations.{$lang->code}.footer_description", $trans['footer_description'] ?? $siteInfo->footer_description) }}</textarea>
                                            @error("translations.{$lang->code}.footer_description")
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                @if($hasTranslatableExtraFields)
                                <div class="mt-4">
                                    @include('cms-kit::partials.extra-fields-translatable', [
                                        'configKey' => 'site-information',
                                        'lang' => $lang,
                                        'existingTranslations' => $siteInfo->translations ?? [],
                                        'excludeKeys' => ['security_settings', 'cookie_policy'], // rendered in the Legal Content block above instead
                                    ])
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-primary">Contact Channels</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if(collect(range(1, 4))->contains(fn($i) => $siteInfoConfig["phone_{$i}"] ?? true))
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold mb-3 text-secondary">Phone Numbers</h6>
                                @for($i = 1; $i <= 4; $i++)
                                @php $field = "phone_$i"; @endphp
                                @if($siteInfoConfig[$field] ?? true)
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Phone {{ $i }} {!! in_array($field, $siteInfoRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                    <input type="text" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror" value="{{ old($field, $siteInfo->$field) }}" {{ in_array($field, $siteInfoRequired) ? 'required' : '' }}>
                                    @error($field)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                                @endfor
                                @if($siteInfoConfig['toll_free'] ?? true)
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Toll Free</label>
                                    <input type="text" name="toll_free" class="form-control @error('toll_free') is-invalid @enderror" value="{{ old('toll_free', $siteInfo->toll_free) }}" placeholder="e.g. 800644489">
                                    @error('toll_free')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                            </div>
                            @endif
                            @if(collect(range(1, 4))->contains(fn($i) => $siteInfoConfig["email_{$i}"] ?? true))
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold mb-3 text-secondary">Email Addresses</h6>
                                @for($i = 1; $i <= 4; $i++)
                                @php $field = "email_$i"; @endphp
                                @if($siteInfoConfig[$field] ?? true)
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Email {{ $i }} {!! in_array($field, $siteInfoRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                    <input type="email" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror" value="{{ old($field, $siteInfo->$field) }}" {{ in_array($field, $siteInfoRequired) ? 'required' : '' }}>
                                    @error($field)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                                @endfor
                            </div>
                            @endif
                            @if($siteInfoConfig['whatsapp_number'] ?? true)
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Main WhatsApp Number</label>
                                <input type="text" name="whatsapp_number" class="form-control @error('whatsapp_number') is-invalid @enderror" value="{{ old('whatsapp_number', $siteInfo->whatsapp_number) }}" placeholder="e.g. +1234567890">
                                @error('whatsapp_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                            @if($siteInfoConfig['receipt_email'] ?? true)
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Recipient Email {!! in_array('receipt_email', $siteInfoRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                <input type="email" name="receipt_email" class="form-control @error('receipt_email') is-invalid @enderror" value="{{ old('receipt_email', $siteInfo->receipt_email) }}" placeholder="e.g. billing@company.com" {{ in_array('receipt_email', $siteInfoRequired) ? 'required' : '' }}>
                                @error('receipt_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h5 class="mb-0 fw-bold">Visual Assets</h5>
                    </div>
                    <div class="card-body">
                        @if($siteInfoConfig['logo'] ?? true)
                        <div class="mb-4">
                            <label class="form-label fw-bold">Main Logo {!! in_array('logo', $siteInfoRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <small class="text-muted d-block mb-1">Recommended size: 200x60px (PNG/SVG)</small>
                            @if($siteInfo->logo)
                            <div class="mb-2">
                                <img src="{{ media_url($siteInfo->logo) }}" class="img-thumbnail rounded" style="max-height: 80px;">
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="remove_logo" id="removeSiteLogo" value="1" {{ old('remove_logo') ? 'checked' : '' }}>
                                <label class="form-check-label" for="removeSiteLogo">Remove current logo</label>
                            </div>
                            @endif
                            <input type="file" name="logo" class="form-control mb-2 @error('logo') is-invalid @enderror" {{ in_array('logo', $siteInfoRequired) && !$siteInfo->logo ? 'required' : '' }}>
                            @error('logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if($siteInfoConfig['logo_alt'] ?? true)
                            <input type="text" name="logo_alt" class="form-control @error('logo_alt') is-invalid @enderror" placeholder="Logo Alt Text" value="{{ old('logo_alt', $siteInfo->logo_alt) }}">
                            @error('logo_alt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @endif
                        </div>
                        @endif

                        @if(config('cms-kit.database.site-information.extra_fields.logo_colour'))
                        @php $logoColour = $siteInfo->extra_fields['logo_colour'] ?? null; @endphp
                        <div class="mb-4">
                            <label class="form-label fw-bold">Colour Logo</label>
                            <small class="text-muted d-block mb-1">Recommended size: 200x60px (PNG/SVG). An alternate colour version of the main logo.</small>
                            @if($logoColour)
                            <div class="mb-2">
                                <img src="{{ media_url($logoColour) }}" class="img-thumbnail rounded" style="max-height: 80px;">
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="remove_extra_fields_logo_colour" id="removeSiteLogoColour" value="1" {{ old('remove_extra_fields_logo_colour') ? 'checked' : '' }}>
                                <label class="form-check-label" for="removeSiteLogoColour">Remove current colour logo</label>
                            </div>
                            @endif
                            <input type="file" name="extra_fields[logo_colour]" class="form-control mb-2 @error('extra_fields.logo_colour') is-invalid @enderror">
                            @error('extra_fields.logo_colour')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @if($siteInfoConfig['favicon'] ?? true)
                        <div class="mb-4">
                            <label class="form-label fw-bold">Favicon {!! in_array('favicon', $siteInfoRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <small class="text-muted d-block mb-1">Recommended size: 32x32px (ICO/PNG)</small>
                            @if($siteInfo->favicon)
                            <div class="mb-2">
                                <img src="{{ media_url($siteInfo->favicon) }}" class="img-thumbnail rounded" style="max-height: 32px;">
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="remove_favicon" id="removeSiteFavicon" value="1" {{ old('remove_favicon') ? 'checked' : '' }}>
                                <label class="form-check-label" for="removeSiteFavicon">Remove current favicon</label>
                            </div>
                            @endif
                            <input type="file" name="favicon" class="form-control @error('favicon') is-invalid @enderror" {{ in_array('favicon', $siteInfoRequired) && !$siteInfo->favicon ? 'required' : '' }}>
                            @error('favicon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @if($siteInfoConfig['footer_logo'] ?? true)
                        <div class="mb-0">
                            <label class="form-label fw-bold">Footer Logo</label>
                            <small class="text-muted d-block mb-1">Recommended size: 150x50px (PNG/SVG)</small>
                            @if($siteInfo->footer_logo)
                            <div class="mb-2">
                                <img src="{{ media_url($siteInfo->footer_logo) }}" class="img-thumbnail rounded" style="max-height: 80px;">
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="remove_footer_logo" id="removeSiteFooterLogo" value="1" {{ old('remove_footer_logo') ? 'checked' : '' }}>
                                <label class="form-check-label" for="removeSiteFooterLogo">Remove current footer logo</label>
                            </div>
                            @endif
                            <input type="file" name="footer_logo" class="form-control mb-2 @error('footer_logo') is-invalid @enderror">
                            @error('footer_logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if($siteInfoConfig['footer_logo_alt'] ?? true)
                            <input type="text" name="footer_logo_alt" class="form-control @error('footer_logo_alt') is-invalid @enderror" placeholder="Footer Logo Alt Text" value="{{ old('footer_logo_alt', $siteInfo->footer_logo_alt) }}">
                            @error('footer_logo_alt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @endif
                        </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h5 class="mb-0 fw-bold">Social Media</h5>
                    </div>
                    <div class="card-body">
                        @foreach($socialFields as $inputKey => $displayLabel)
                            @if($siteInfoConfig[$inputKey] ?? true)
                            <div class="mb-2">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-{{ $inputKey == 'whatsapp_social' ? 'whatsapp' : str_replace('_', '-', $inputKey) }}"></i></span>
                                    <input type="url" name="{{ $inputKey }}" class="form-control @error($inputKey) is-invalid @enderror" placeholder="{{ $displayLabel }} URL" value="{{ old($inputKey, $siteInfo->$inputKey) }}">
                                </div>
                                @error($inputKey)
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h5 class="mb-0 fw-bold">Extra SEO & Tracking</h5>
                        <small class="text-muted">Google Tag Manager and other tracking/SEO scripts. These are injected site-wide in the front-end layout.</small>
                    </div>
                    <div class="card-body">
                        @if($siteInfoConfig['gtag'] ?? true)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Google Tag Manager container ID(s)</label>
                            <textarea name="gtag" class="form-control" rows="2" placeholder="One per line, e.g.&#10;GTM-P0GFW4PT&#10;GTM-W7VXZ48P">{{ old('gtag', $siteInfo->gtag) }}</textarea>
                            <small class="text-muted">Enter one GTM container ID per line (e.g. GTM-XXXXXXX). The standard GTM script and noscript iframe will be added automatically.</small>
                        </div>
                        @endif
                        @if($siteInfoConfig['custom_head_script'] ?? true)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Custom head scripts</label>
                            <textarea name="custom_head_script" class="form-control" rows="4" placeholder="Paste any <script>, <meta>, or other HTML to inject in <head>">{{ old('custom_head_script', $siteInfo->custom_head_script) }}</textarea>
                            <small class="text-muted">Optional. Raw HTML injected before &lt;/head&gt;. Use for analytics, verification tags, etc.</small>
                        </div>
                        @endif
                        @if($siteInfoConfig['custom_body_script'] ?? true)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Custom body scripts</label>
                            <textarea name="custom_body_script" class="form-control" rows="4" placeholder="Paste any <noscript>, <script>, or other HTML to inject at start of <body>">{{ old('custom_body_script', $siteInfo->custom_body_script) }}</textarea>
                            <small class="text-muted">Optional. Raw HTML injected right after &lt;body&gt;. Use for GTM noscript fallbacks or other body-level snippets.</small>
                        </div>
                        @endif
                    </div>
                </div>

                @include('cms-kit::partials.extra-fields-global', [
                    'configKey' => 'site-information',
                    'existingValues' => $siteInfo->extra_fields ?? [],
                    'excludeKeys' => ['logo_colour'], // rendered in the Visual Assets card above instead
                ])
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-5">
            <button type="submit" class="btn btn-primary btn-lg px-5">Save Information</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('invalid', function(e) {
        let invalidTabPane = e.target.closest('.tab-pane');
        if (invalidTabPane) {
            let tabId = invalidTabPane.id;
            let tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
            if (tabBtn && !tabBtn.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                setTimeout(() => { e.target.focus(); }, 150);
            }
        }
    }, true);

    var common = {
        height: 350,
        plugins: 'lists link image code',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
        branding: false,
        promotion: false
    };
    if (typeof tinymce !== 'undefined') {
        tinymce.init(Object.assign({ selector: '.tinymce-site-info' }, common));
    }
</script>
@endpush
