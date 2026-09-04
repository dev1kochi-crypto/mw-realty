@php
    $item = $testimonial ?? null;
    $itemRequired = config('cms-kit.database.testimonials.items.required', []);
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
@endphp

<div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.85rem;">
    <i class="fas fa-info-circle text-primary me-2"></i> <strong>Note:</strong> Please ensure all required fields <span class="text-danger">(*)</span> are filled{{ $showLanguageUi ? ' across all language tabs' : '' }}.
</div>

@if($showLanguageUi)
<ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="testimonialFormTabs" role="tablist">
    @foreach($languages as $lang)
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $loop->first ? 'active' : '' }} px-3 py-2 fw-medium" id="testimonial-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#testimonial-{{ $lang->code }}" type="button" role="tab">
            <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
        </button>
    </li>
    @endforeach
</ul>
@endif

<div class="tab-content mb-4 language-switcher-content" id="testimonialTabsContent">
    @foreach($languages as $lang)
    @php $trans = $item->translations[$lang->code] ?? []; @endphp
    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="testimonial-{{ $lang->code }}" role="tabpanel">
        <div class="mb-3">
            <label class="form-label fw-bold">Name{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }} {!! in_array('name', $itemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
            <input type="text" name="translations[{{ $lang->code }}][name]" class="form-control @error("translations.{$lang->code}.name") is-invalid @enderror" value="{{ old("translations.{$lang->code}.name", $trans['name'] ?? '') }}" {{ in_array('name', $itemRequired) ? 'required' : '' }}>
            @error("translations.{$lang->code}.name")
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Designation{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }} {!! in_array('designation', $itemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
            <input type="text" name="translations[{{ $lang->code }}][designation]" class="form-control @error("translations.{$lang->code}.designation") is-invalid @enderror" value="{{ old("translations.{$lang->code}.designation", $trans['designation'] ?? '') }}" {{ in_array('designation', $itemRequired) ? 'required' : '' }}>
            @error("translations.{$lang->code}.designation")
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        @if(config('cms-kit.database.testimonials.items.content'))
        <div class="mb-3">
            <label class="form-label fw-bold">Content{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }} {!! in_array('content', $itemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
            <textarea name="translations[{{ $lang->code }}][content]" class="form-control tinymce-editor @error("translations.{$lang->code}.content") is-invalid @enderror" rows="4">{{ old("translations.{$lang->code}.content", $trans['content'] ?? '') }}</textarea>
            @error("translations.{$lang->code}.content")
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        @endif

        @include('cms-kit::partials.extra-fields-translatable', [
            'configKey' => 'testimonials.items',
            'lang' => $lang,
            'existingTranslations' => $item->translations ?? [],
        ])
    </div>
    @endforeach
</div>

<hr class="my-4">

<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label fw-bold">Image {!! in_array('image', $itemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
        <div class="alert alert-light border py-1 px-2 mb-2 shadow-sm" style="font-size: 0.75rem;">
            <i class="fas fa-info-circle text-primary me-1"></i> This image is used across all languages.
        </div>
        <input type="file" name="image" class="form-control mb-2 @error('image') is-invalid @enderror" {{ in_array('image', $itemRequired) && !$item?->image ? 'required' : '' }}>
        @error('image')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if(config('cms-kit.database.testimonials.items.image_alt'))
        <input type="text" name="image_alt" class="form-control mt-2" placeholder="Image Alt Text" value="{{ old('image_alt', $item->image_alt ?? '') }}">
        @endif
        <small class="text-muted d-block mt-2">Recommended: {{ config('cms-kit.images.testimonials.item_image.width') }}x{{ config('cms-kit.images.testimonials.item_image.height') }}px</small>
        @if($item?->image)
            <div class="mt-2 position-relative d-inline-block">
                <img src="{{ asset('storage/'.$item->image) }}" class="rounded shadow-sm" style="height: 60px; width: 60px; object-fit: cover;">
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary border border-light">Current</span>
            </div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_image" id="removeTestimonialImage" value="1" {{ old('remove_image') ? 'checked' : '' }}>
                <label class="form-check-label" for="removeTestimonialImage">Remove current image</label>
            </div>
        @endif
    </div>
    @if(config('cms-kit.database.testimonials.items.rating'))
    <div class="col-md-6 text-center">
        <label class="form-label fw-bold d-block text-start">Rating {!! in_array('rating', $itemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
        <div class="rating-input d-inline-flex bg-light p-3 rounded-pill shadow-sm">
            <select name="rating" class="form-select border-0 bg-transparent fw-bold text-warning h4 mb-0" style="width: auto;" {{ in_array('rating', $itemRequired) ? 'required' : '' }}>
                <option value="5" {{ old('rating', $item->rating ?? 5) == 5 ? 'selected' : '' }}>5 Stars</option>
                <option value="4" {{ old('rating', $item->rating ?? 5) == 4 ? 'selected' : '' }}>4 Stars</option>
                <option value="3" {{ old('rating', $item->rating ?? 5) == 3 ? 'selected' : '' }}>3 Stars</option>
                <option value="2" {{ old('rating', $item->rating ?? 5) == 2 ? 'selected' : '' }}>2 Stars</option>
                <option value="1" {{ old('rating', $item->rating ?? 5) == 1 ? 'selected' : '' }}>1 Star</option>
            </select>
        </div>
    </div>
    @endif
</div>

@include('cms-kit::partials.extra-fields-global', [
    'configKey' => 'testimonials.items',
    'existingValues' => $item->extra_fields ?? [],
])

<div class="row align-items-center mt-4 pt-3 border-top">
    <div class="col-md-4">
        <label class="form-label fw-bold">Order Index</label>
        <input type="number" name="order_index" value="{{ old('order_index', $item->order_index ?? $nextOrder) }}" class="form-control shadow-sm" min="1">
    </div>
    <div class="col-md-8">
        <div class="form-check form-switch pt-4">
            <input class="form-check-input h5 mb-0" type="checkbox" name="status" {{ old('status', $item->status ?? true) ? 'checked' : '' }} id="statusSwitch">
            <label class="form-check-label fw-bold ms-2 mt-1" for="statusSwitch">Status (ON/OFF)</label>
        </div>
    </div>
</div>
