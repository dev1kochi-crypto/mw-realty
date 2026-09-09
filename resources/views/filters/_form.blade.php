@php
    $isEdit = isset($filter);
    $showOn = old('show_on', $isEdit ? ($filter->show_on ?? ['home', 'listing']) : ['home', 'listing']);
@endphp

<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label fw-bold">Key <span class="text-danger">*</span></label>
        <input type="text" name="key" class="form-control @error('key') is-invalid @enderror" value="{{ old('key', $isEdit ? $filter->key : '') }}" placeholder="e.g. property_type" required>
        <small class="text-muted">Must match a property field this filter queries (property_type, location, listing_type, completion_status, bedrooms, bathrooms, price, sqft) — or a custom column added later.</small>
        @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-bold">Type <span class="text-danger">*</span></label>
        <select name="type" class="form-select" required {{ $isEdit && $filter->values->count() ? 'disabled' : '' }}>
            <option value="select" {{ old('type', $isEdit ? $filter->type : '') == 'select' ? 'selected' : '' }}>Select (uses option values below)</option>
            <option value="range" {{ old('type', $isEdit ? $filter->type : '') == 'range' ? 'selected' : '' }}>Range (min/max, e.g. Price)</option>
            <option value="number" {{ old('type', $isEdit ? $filter->type : '') == 'number' ? 'selected' : '' }}>Number</option>
        </select>
        @if($isEdit && $filter->values->count())
            <input type="hidden" name="type" value="{{ $filter->type }}">
            <small class="text-muted">Type is locked while option values exist.</small>
        @endif
    </div>
</div>

<div class="row g-4 mt-1">
    @foreach($languages as $lang)
    <div class="col-md-6">
        <label class="form-label fw-bold">Label ({{ $lang->name }}) <span class="text-danger">*</span></label>
        <input type="text" name="translations[{{ $lang->code }}][label]" class="form-control @error("translations.{$lang->code}.label") is-invalid @enderror"
               value="{{ old("translations.{$lang->code}.label", $isEdit ? ($filter->translations[$lang->code]['label'] ?? '') : '') }}" required>
        @error("translations.{$lang->code}.label")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @endforeach
</div>

<div class="row g-4 mt-1">
    <div class="col-md-6">
        <label class="form-label fw-bold d-block">Show On</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="show_on[]" value="home" id="showHome" {{ in_array('home', $showOn) ? 'checked' : '' }}>
            <label class="form-check-label" for="showHome">Home Banner</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="show_on[]" value="listing" id="showListing" {{ in_array('listing', $showOn) ? 'checked' : '' }}>
            <label class="form-check-label" for="showListing">Properties Listing</label>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $isEdit ? $filter->order_index : ($nextOrder ?? 1)) }}" min="1">
    </div>
    <div class="col-md-3 d-flex align-items-end pb-2">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="status" id="filterStatus" {{ old('status', $isEdit ? $filter->status : true) ? 'checked' : '' }}>
            <label class="form-check-label" for="filterStatus">Active (shown on frontend)</label>
        </div>
    </div>
</div>
