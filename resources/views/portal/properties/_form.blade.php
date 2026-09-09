@php
    $isEdit = isset($property);
    $details = $isEdit ? $property->details : null;
    $val = fn ($field, $default = null) => old($field, $isEdit ? ($property->{$field} ?? $default) : $default);
    $detailVal = fn ($field, $default = null) => old($field, $isEdit && $details ? ($details->{$field} ?? $default) : $default);
@endphp

<div class="portal-card p-4 mb-4">
    <div class="portal-section-title">Basic Information</div>
    <ul class="nav nav-pills mb-4" id="langTabs" role="tablist" style="gap:0.5rem;">
        @foreach($languages as $lang)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $loop->first ? 'active' : '' }}" style="border-radius:50px;" id="{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $lang->code }}-content" type="button" role="tab">
                {{ $lang->name }}
            </button>
        </li>
        @endforeach
    </ul>

    <div class="tab-content mb-2">
        @foreach($languages as $lang)
        @php $t = $isEdit ? ($property->translations[$lang->code] ?? []) : []; @endphp
        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control" value="{{ old("translations.{$lang->code}.title", $t['title'] ?? '') }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="translations[{{ $lang->code }}][description]" class="form-control" rows="3">{{ old("translations.{$lang->code}.description", $t['description'] ?? '') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Address</label>
                    <input type="text" name="translations[{{ $lang->code }}][address]" class="form-control" value="{{ old("translations.{$lang->code}.address", $t['address'] ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">City</label>
                    <input type="text" name="translations[{{ $lang->code }}][city]" class="form-control" value="{{ old("translations.{$lang->code}.city", $t['city'] ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Country</label>
                    <input type="text" name="translations[{{ $lang->code }}][country]" class="form-control" value="{{ old("translations.{$lang->code}.country", $t['country'] ?? '') }}">
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<div class="portal-card p-4 mb-4">
    <div class="portal-section-title">Listing Details</div>
    <div class="row g-3">
        @php
            $selectFields = [
                'listing_type' => 'Listing Type (Buy / Rent)',
                'completion_status' => 'Completion Status',
                'property_type' => 'Property Type',
                'location' => 'Location',
            ];
        @endphp
        @foreach($selectFields as $field => $label)
        <div class="col-md-6">
            <label class="form-label fw-semibold">{{ $label }}</label>
            @if(isset($filterOptions[$field]) && $filterOptions[$field]->activeValues->count())
                <select name="{{ $field }}" class="form-select">
                    <option value="">-- Select --</option>
                    @foreach($filterOptions[$field]->activeValues as $option)
                    <option value="{{ $option->value }}" {{ $val($field) == $option->value ? 'selected' : '' }}>{{ $option->getTranslation('label') }}</option>
                    @endforeach
                </select>
            @else
                <input type="text" name="{{ $field }}" class="form-control" value="{{ $val($field) }}" placeholder="Free text (no options configured yet)">
            @endif
        </div>
        @endforeach

        <div class="col-md-3">
            <label class="form-label fw-semibold">Bedrooms</label>
            <input type="number" name="bedrooms" class="form-control" value="{{ $val('bedrooms') }}" min="0">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Bathrooms</label>
            <input type="number" name="bathrooms" class="form-control" value="{{ $val('bathrooms') }}" min="0">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Sqft</label>
            <input type="number" name="sqft" class="form-control" value="{{ $val('sqft') }}" min="0">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold">Price</label>
            <input type="number" step="0.01" name="price" class="form-control" value="{{ $val('price') }}" min="0">
        </div>
        <div class="col-md-1">
            <label class="form-label fw-semibold">Currency</label>
            <input type="text" name="currency" class="form-control" value="{{ $val('currency', 'AED') }}">
        </div>
    </div>
</div>

<div class="portal-card p-4 mb-4">
    <div class="portal-section-title">Additional Details</div>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Year Built</label>
            <input type="number" name="year_built" class="form-control" value="{{ $detailVal('year_built') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Floor</label>
            <input type="text" name="floor" class="form-control" value="{{ $detailVal('floor') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Parking Spaces</label>
            <input type="number" name="parking" class="form-control" value="{{ $detailVal('parking') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">View</label>
            <input type="text" name="view" class="form-control" value="{{ $detailVal('view') }}" placeholder="e.g. Sea View">
        </div>
        <div class="col-md-8">
            <label class="form-label fw-semibold">Amenities</label>
            <textarea name="amenities_raw" class="form-control" rows="3" placeholder="One per line, e.g. Pool, Gym, Parking">{{ $isEdit && $details ? implode("\n", $details->amenities ?? []) : '' }}</textarea>
        </div>
        <div class="col-md-4 d-flex align-items-center">
            <div class="form-check form-switch mt-4">
                <input class="form-check-input" type="checkbox" name="furnished" id="furnished" {{ $detailVal('furnished') ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="furnished">Furnished</label>
            </div>
        </div>
    </div>
</div>

<div class="portal-card p-4 mb-4">
    <div class="portal-section-title">Photos</div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Cover Photo</label>
            <input type="file" name="image" class="form-control">
            <input type="text" name="image_alt" class="form-control mt-2" placeholder="Image ALT text" value="{{ $val('image_alt') }}">
            @if($isEdit && $property->image)
                <img src="{{ asset('storage/' . $property->image) }}" class="mt-2" style="height:70px;border-radius:8px;">
            @endif
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Gallery Photos</label>
            <input type="file" name="images[]" class="form-control" multiple accept="image/*">
            @if($isEdit && $property->images->count())
            <div class="d-flex flex-wrap gap-2 mt-2">
                @foreach($property->images as $image)
                <div class="position-relative" style="width:80px;">
                    <img src="{{ asset('storage/' . $image->image) }}" style="width:80px;height:56px;object-fit:cover;border-radius:8px;">
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0 gallery-image-delete"
                            style="width:18px;height:18px;line-height:1;border-radius:50%;" data-property="{{ $property->id }}" data-id="{{ $image->id }}">
                        <i class="fas fa-times" style="font-size:9px;"></i>
                    </button>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

<div class="d-flex align-items-center gap-4 mb-4">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="status" id="propertyStatus" {{ $val('status', true) ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="propertyStatus">Active (visible on site)</label>
    </div>
    @if($isAdmin ?? false)
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="featured" id="propertyFeatured" {{ $val('featured') ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="propertyFeatured">Featured</label>
    </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (!form) return;
    form.addEventListener('submit', function () {
        const raw = form.querySelector('textarea[name="amenities_raw"]');
        if (!raw) return;
        raw.value.split('\n').map(v => v.trim()).filter(Boolean).forEach(function (v, i) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'amenities[' + i + ']';
            input.value = v;
            form.appendChild(input);
        });
    });
});

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.gallery-image-delete');
    if (!btn) return;
    if (!confirm('Delete this image?')) return;
    fetch("{{ url('portal/properties') }}/" + btn.dataset.property + "/images/" + btn.dataset.id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    }).then(() => location.reload());
});
</script>
