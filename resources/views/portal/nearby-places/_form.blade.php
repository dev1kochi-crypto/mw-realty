@php
    $isEdit = isset($place);
    $val = fn ($field, $default = null) => old($field, $isEdit ? ($place->{$field} ?? $default) : $default);
@endphp

<div class="nearby-place-form">
    <div class="portal-card p-4 mb-3">
        @if($languages->count() > 1)
        <ul class="nav portal-lang-tabs mb-4" id="languageTabs" role="tablist">
            @foreach($languages as $lang)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#panel-{{ $lang->code }}" type="button" role="tab">
                    <i class="fas fa-language me-1 opacity-75"></i>{{ $lang->name }}
                </button>
            </li>
            @endforeach
        </ul>
        @endif

        <div class="tab-content">
            @foreach($languages as $lang)
            @php $t = $isEdit ? ($place->translations[$lang->code] ?? []) : []; @endphp
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="panel-{{ $lang->code }}" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="translations[{{ $lang->code }}][name]" class="form-control @error("translations.{$lang->code}.name") is-invalid @enderror" value="{{ old("translations.{$lang->code}.name", $t['name'] ?? '') }}" required>
                        @error("translations.{$lang->code}.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" name="translations[{{ $lang->code }}][address]" class="form-control" value="{{ old("translations.{$lang->code}.address", $t['address'] ?? '') }}">
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <hr class="my-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Latitude <span class="text-danger">*</span></label>
                <input type="text" name="latitude" class="form-control @error('latitude') is-invalid @enderror" value="{{ $val('latitude') }}" required>
                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Longitude <span class="text-danger">*</span></label>
                <input type="text" name="longitude" class="form-control @error('longitude') is-invalid @enderror" value="{{ $val('longitude') }}" required>
                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                <select name="category" class="form-select nearby-type-select @error('category') is-invalid @enderror" required>
                    <option value="">Search type</option>
                    @foreach($typeFilter?->activeValues ?? [] as $option)
                    <option value="{{ $option->value }}" {{ old('category', $place->category ?? '') == $option->value ? 'selected' : '' }}>{{ $option->getTranslation('label') }}</option>
                    @endforeach
                </select>
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <hr class="my-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="status" id="placeStatus" value="1" {{ $val('status', true) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="placeStatus">Active</label>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-portal-primary px-4"><i class="fas fa-save me-1"></i> Save Place</button>
        <a href="{{ route('portal.nearby-places.index') }}" class="btn portal-btn-ghost">Cancel</a>
    </div>
</div>

<style>
    .nearby-place-form .form-label { font-size: 0.85rem; color: var(--portal-text); margin-bottom: 0.4rem; }
    .nearby-place-form .form-control,
    .nearby-place-form .form-select {
        border: 1.5px solid var(--portal-border);
        border-radius: 10px;
        padding: 0.6rem 0.85rem;
        font-size: 0.9rem;
        background: var(--portal-surface);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .nearby-place-form .form-control:focus,
    .nearby-place-form .form-select:focus {
        border-color: var(--portal-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
    }
    .nearby-place-form .form-check-input { width: 2.4em; height: 1.3em; cursor: pointer; }
    .nearby-place-form .form-check-input:checked { background-color: var(--portal-primary); border-color: var(--portal-primary); }

    .nearby-place-form .select2-container--default .select2-selection--single {
        height: auto;
        border: 1.5px solid var(--portal-border);
        border-radius: 10px;
        padding: 0.6rem 0.85rem;
        background: var(--portal-surface);
    }
    .nearby-place-form .select2-container--default.select2-container--open .select2-selection--single,
    .nearby-place-form .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--portal-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
    }
    .nearby-place-form .select2-selection__rendered { line-height: 1.3 !important; font-size: 0.9rem; color: var(--portal-text) !important; padding: 0 !important; }
    .nearby-place-form .select2-selection__arrow { height: 100% !important; top: 0 !important; right: 10px !important; }
</style>
