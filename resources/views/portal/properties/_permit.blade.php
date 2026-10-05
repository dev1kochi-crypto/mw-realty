{{--
    Core details › advertising permit, by emirate (App\Support\PermitRules):
      Dubai              → Permit type: RERA (license + permit + Validate) · DTCM (permit, rent only) · None (DIFC/JAFZA)
      Abu Dhabi          → Broker license + ADREC permit + Validate
      Northern Emirates  → City: Al Ain → as Abu Dhabi · any other city → no permit
    Validate asks DLD / ADREC (PortalPropertyController::validatePermit); a verified permit fills in
    and locks the listing fields it covers. Behaviour: the "Permit" script in _form.
--}}
@php
    $permitLocked = fn (string $field) => in_array($field, $lockedFields ?? [], true);
    $permitTypeVal = old('permit_type', $isEdit && in_array($property->permit_type, \App\Support\PermitRules::DUBAI_TYPES, true) ? $property->permit_type : 'rera');
    $permitCityVal = (string) old('permit_city', $isEdit ? $property->permit_city : '');
    $verifiedVia = $isEdit && $property->permit_verified_at ? $property->permit_verified_via : null;
    $permitDate = old('permit_expires_at', $isEdit && $property->permit_expires_at ? $property->permit_expires_at->format('Y-m-d') : '');
    // Initial status box: what we already know about the saved permit.
    $permitStatus = match (true) {
        in_array($verifiedVia, ['dld', 'adrec'], true) => ['verified', 'Verification successful', 'Verified with ' . strtoupper($verifiedVia) . ' on ' . $property->permit_verified_at->format('d M Y') . '.'],
        $verifiedVia === 'manual' => ['verified', 'Verified', 'Verified on ' . $property->permit_verified_at->format('d M Y') . '.'],
        $isEdit && filled($property->permit_number) => ['pending', 'Not verified yet', 'Validate the permit, or save — Super Admin checks it before the listing goes live.'],
        default => ['idle', 'Ready to validate', 'Enter the permit number above.'],
    };
@endphp
<div id="permitBlock" class="permit-block"
     data-validate-url="{{ route('portal.properties.permit.validate') }}"
     data-property-id="{{ $isEdit ? $property->id : '' }}"
     data-types='@json(collect(\App\Support\PermitRules::TYPES)->map(fn ($t) => ['number_label' => $t['number_label'], 'license_label' => $t['license_label'], 'validates' => $t['validates']]))'
     data-licenses='@json($permitLicenses ?? [])'
     data-verified="{{ in_array($verifiedVia, ['dld', 'adrec'], true) ? '1' : '' }}">
    <div class="row g-3">
        <div class="col-12">
            @include('portal.properties._option-select', $opt(\App\Models\Filter::EMIRATE_KEY, 'Emirate', ['required' => true, 'current' => $val('emirate', 'dubai')]))
        </div>

        {{-- Northern Emirates: the city decides whether a permit is needed. --}}
        <div class="col-12" data-permit-show="northern">
            <label class="form-label fw-semibold" for="permitCity">City <span class="text-danger">*</span>
                @if($permitLocked('permit_city'))<i class="fas fa-lock text-muted ms-1 small" title="Matches the verified permit"></i>@endif</label>
            <select id="permitCity" @unless($permitLocked('permit_city')) name="permit_city" @endunless class="form-select @error('permit_city') is-invalid @enderror" @disabled($permitLocked('permit_city'))>
                <option value="">Select an option</option>
                @foreach(\App\Support\PermitRules::NORTHERN_CITIES as $value => $label)
                <option value="{{ $value }}" @selected($permitCityVal === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if($permitLocked('permit_city'))<input type="hidden" name="permit_city" value="{{ $permitCityVal }}">@endif
            @error('permit_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        {{-- Dubai: which authority issued the permit. --}}
        <div class="col-12" data-permit-show="dubai">
            <label class="form-label fw-semibold d-block">Permit type <span class="text-danger">*</span>
                <i class="fas fa-circle-info text-muted ms-1" tabindex="0" data-bs-toggle="tooltip" title="RERA: DLD (Trakheesi) advertising permit. DTCM: holiday-home permit — rent only. None: DIFC / JAFZA free zones, where no permit is issued."></i>
                @if($permitLocked('permit_type'))<i class="fas fa-lock text-muted ms-1 small" title="Matches the verified permit"></i>@endif</label>
            <div class="property-segmented property-segmented--wide" role="radiogroup" aria-label="Permit type">
                @foreach(\App\Support\PermitRules::DUBAI_TYPES as $type)
                <input type="radio" class="btn-check" @unless($permitLocked('permit_type')) name="permit_type" @endunless id="permitType-{{ $type }}" value="{{ $type }}" @checked($permitTypeVal === $type) @disabled($permitLocked('permit_type'))>
                <label for="permitType-{{ $type }}">{{ \App\Support\PermitRules::TYPES[$type]['label'] }}</label>
                @endforeach
            </div>
            @if($permitLocked('permit_type'))<input type="hidden" name="permit_type" value="{{ $permitTypeVal }}">@endif
        </div>

        {{-- License the permit is issued under (RERA: company ORN · ADREC: brokerage registration no.). --}}
        <div class="col-12" data-permit-show="licensed">
            <label class="form-label fw-semibold" for="permitLicense"><span data-permit-license-label>Real estate company license</span> <span class="text-danger">*</span></label>
            <select id="permitLicense" class="form-select" disabled aria-describedby="permitLicenseHelp"></select>
            <div class="form-text" id="permitLicenseHelp" data-permit-license-help></div>
            <div class="alert alert-warning small py-2 mt-2 mb-0 d-none" data-permit-license-missing>
                <i class="fas fa-triangle-exclamation me-1"></i><span data-permit-license-missing-text></span>
                <a href="#" target="_blank" class="ms-1 fw-semibold d-none" data-permit-license-missing-link></a>
            </div>
        </div>

        {{-- Permit number (+ Validate for RERA / ADREC). --}}
        <div class="col-12" data-permit-show="numbered">
            <label class="form-label fw-semibold" for="permitNumber"><span data-permit-number-label>RERA permit number</span> <span class="text-danger">*</span>
                <i class="fas fa-circle-info text-muted ms-1" tabindex="0" data-bs-toggle="tooltip" title="The advertising permit number issued for this listing. One permit covers one listing only."></i>
                @if($permitLocked('permit_number'))<i class="fas fa-lock text-muted ms-1 small" title="Matches the verified permit"></i>@endif</label>
            <div class="d-flex gap-2 align-items-start">
                <input type="text" id="permitNumber" name="permit_number" class="form-control @error('permit_number') is-invalid @enderror" value="{{ $val('permit_number') }}" maxlength="64" placeholder="e.g. 7112345678" autocomplete="off" @readonly($permitLocked('permit_number'))>
                <button type="button" class="btn portal-btn-ghost permit-validate-btn" id="permitValidate" data-permit-show="validates">
                    {{ in_array($verifiedVia, ['dld', 'adrec'], true) ? 'Refresh' : 'Validate' }}
                </button>
            </div>
            @error('permit_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <input type="hidden" name="permit_token" id="permitToken" value="">

            <div class="permit-status is-{{ $permitStatus[0] }}" id="permitStatus" data-permit-show="validates" role="status" aria-live="polite">
                <span class="permit-status-icon"><i class="fas"></i></span>
                <div><strong>{{ $permitStatus[1] }}</strong><span>{{ $permitStatus[2] }}</span></div>
            </div>
        </div>

        <div class="col-md-6" data-permit-show="numbered">
            <label class="form-label fw-semibold" for="permitExpires">Permit expiry date <span class="text-danger">*</span></label>
            <input type="date" id="permitExpires" name="permit_expires_at" class="form-control @error('permit_expires_at') is-invalid @enderror" value="{{ $permitDate }}">
            @error('permit_expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">The listing is taken off the website automatically when the permit expires.</div>
        </div>
        <div class="col-md-6" data-permit-show="qr">
            <label class="form-label fw-semibold">Permit QR code <span class="text-danger">*</span></label>
            <input type="file" name="permit_qr" class="form-control @error('permit_qr') is-invalid @enderror" accept="image/*">
            @error('permit_qr')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">The QR image issued with the permit — shown on the listing page so buyers can verify the ad.</div>
            @if($isEdit && $property->permit_qr)
            <img src="{{ media_url($property->permit_qr) }}" alt="Permit QR" class="mt-2 border rounded" style="height: 72px;">
            @endif
        </div>
        <div class="col-12" data-permit-show="numbered">
            <label class="form-label fw-semibold">Permit verification link</label>
            <input type="url" name="permit_verification_url" class="form-control @error('permit_verification_url') is-invalid @enderror" value="{{ $val('permit_verification_url') }}" placeholder="The link the QR code opens (optional)">
            @error('permit_verification_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12" data-permit-show="no-permit">
            <div class="alert alert-light border small mb-0"><i class="fas fa-circle-check text-success me-1"></i>No advertising permit is needed for this listing.</div>
        </div>
    </div>
</div>
<hr class="my-4">
