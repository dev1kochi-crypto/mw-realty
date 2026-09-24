@php
    $item = $testimonial ?? null;
    $itemRequired = config('cms-kit.database.testimonials.items.required', []);
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $currentType = old('type', $item->type ?? 'text');
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
            <label class="form-label fw-bold">Name {!! in_array('name', $itemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
            <input type="text" name="translations[{{ $lang->code }}][name]" class="form-control @error("translations.{$lang->code}.name") is-invalid @enderror" value="{{ old("translations.{$lang->code}.name", $trans['name'] ?? '') }}" {{ in_array('name', $itemRequired) ? 'required' : '' }}>
            @error("translations.{$lang->code}.name")
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Designation {!! in_array('designation', $itemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
            <input type="text" name="translations[{{ $lang->code }}][designation]" class="form-control @error("translations.{$lang->code}.designation") is-invalid @enderror" value="{{ old("translations.{$lang->code}.designation", $trans['designation'] ?? '') }}" {{ in_array('designation', $itemRequired) ? 'required' : '' }}>
            @error("translations.{$lang->code}.designation")
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        @if(config('cms-kit.database.testimonials.items.content'))
        @php $contentRequired = in_array('content', $itemRequired); @endphp
        <div class="mb-3">
            <label class="form-label fw-bold">Content
                <span class="content-required-mark text-danger" style="{{ $contentRequired && $currentType !== 'video' ? '' : 'display:none;' }}">*</span>
            </label>
            <textarea name="translations[{{ $lang->code }}][content]" class="form-control tinymce-editor content-textarea @error("translations.{$lang->code}.content") is-invalid @enderror" rows="4" data-required="{{ $contentRequired ? '1' : '0' }}" {{ $contentRequired && $currentType !== 'video' ? 'required' : '' }}>{{ old("translations.{$lang->code}.content", $trans['content'] ?? '') }}</textarea>
            @error("translations.{$lang->code}.content")
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="form-text">Not required for Video testimonials — the video plays instead of showing this text.</div>
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

<div class="mb-4">
    <label class="form-label fw-bold d-block">Testimonial Type</label>
    <div class="form-check form-check-inline">
        <input class="form-check-input testimonial-type-toggle" type="radio" name="type" id="type_text" value="text" {{ $currentType !== 'video' ? 'checked' : '' }}>
        <label class="form-check-label" for="type_text">Text</label>
    </div>
    <div class="form-check form-check-inline">
        <input class="form-check-input testimonial-type-toggle" type="radio" name="type" id="type_video" value="video" {{ $currentType === 'video' ? 'checked' : '' }}>
        <label class="form-check-label" for="type_video">Video</label>
    </div>
    <div class="form-text">Video testimonials show the photo with a play button; clicking it plays the video below.</div>
</div>

<div id="video-fields" class="row g-3 mb-4 {{ $currentType === 'video' ? '' : 'd-none' }}">
    <div class="col-12">
        <label class="form-label d-block fw-bold">Video Source</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input video-source-toggle" type="radio" name="video_source" id="video_source_url" value="url" {{ old('video_source', $item->video_source ?? 'url') === 'url' ? 'checked' : '' }}>
            <label class="form-check-label" for="video_source_url">Video URL (YouTube/Vimeo)</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input video-source-toggle" type="radio" name="video_source" id="video_source_file" value="file" {{ old('video_source', $item->video_source ?? '') === 'file' ? 'checked' : '' }}>
            <label class="form-check-label" for="video_source_file">Upload Video File</label>
        </div>
        @error('video_source')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12 {{ old('video_source', $item->video_source ?? 'url') === 'file' ? 'd-none' : '' }}" id="video-url-input">
        <label class="form-label">Video URL</label>
        <input type="text" name="video_url" class="form-control @error('video_url') is-invalid @enderror" placeholder="YouTube/Vimeo or self-hosted URL" value="{{ old('video_url', $item->video_url ?? '') }}">
        @error('video_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12 {{ old('video_source', $item->video_source ?? 'url') === 'file' ? '' : 'd-none' }}" id="video-file-input">
        <label class="form-label">Upload Video</label>
        <input type="file" name="video_file" class="form-control @error('video_file') is-invalid @enderror" accept=".mp4,.mov,.avi,video/mp4,video/quicktime,video/x-msvideo">
        @error('video_file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @if(!empty($item?->video_file))
            <small class="text-muted d-block mt-1">Current: {{ basename($item->video_file) }}</small>
        @endif
    </div>
</div>

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
                <img src="{{ media_url($item->image) }}" class="rounded shadow-sm" style="height: 60px; width: 60px; object-fit: cover;">
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary border border-light">Current</span>
            </div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_image" id="removeTestimonialImage" value="1" {{ old('remove_image') ? 'checked' : '' }}>
                <label class="form-check-label" for="removeTestimonialImage">Remove current image</label>
            </div>
        @endif
    </div>
    @if(config('cms-kit.database.testimonials.items.rating'))
    @php $currentRating = (int) old('rating', $item->rating ?? 5); @endphp
    <div class="col-md-6">
        <label class="form-label fw-bold d-block">Rating {!! in_array('rating', $itemRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
        <div class="star-rating-input d-inline-flex align-items-center gap-1">
            @for($i = 1; $i <= 5; $i++)
                <i class="star-choice {{ $i <= $currentRating ? 'fas' : 'far' }} fa-star text-warning" data-value="{{ $i }}" style="cursor: pointer; font-size: 1.1rem;"></i>
            @endfor
            <input type="hidden" name="rating" id="ratingInput" value="{{ $currentRating }}" {{ in_array('rating', $itemRequired) ? 'required' : '' }}>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Text / Video type toggle
    const videoFields = document.getElementById('video-fields');
    const contentTextareas = document.querySelectorAll('.content-textarea');
    const contentMarks = document.querySelectorAll('.content-required-mark');

    function applyType(type) {
        videoFields.classList.toggle('d-none', type !== 'video');
        const isVideo = type === 'video';
        contentTextareas.forEach(function (el) {
            const originallyRequired = el.dataset.required === '1';
            el.required = originallyRequired && !isVideo;
        });
        contentMarks.forEach(function (mark) {
            mark.style.display = isVideo ? 'none' : '';
        });
    }

    document.querySelectorAll('.testimonial-type-toggle').forEach(function (radio) {
        radio.addEventListener('change', function () { applyType(this.value); });
    });
    applyType(document.querySelector('.testimonial-type-toggle:checked')?.value || 'text');

    // Video source (URL vs file) toggle
    const urlInput = document.getElementById('video-url-input');
    const fileInput = document.getElementById('video-file-input');
    document.querySelectorAll('.video-source-toggle').forEach(function (radio) {
        radio.addEventListener('change', function () {
            urlInput.classList.toggle('d-none', this.value === 'file');
            fileInput.classList.toggle('d-none', this.value !== 'file');
        });
    });

    // Clickable star rating
    const stars = document.querySelectorAll('.star-choice');
    const ratingInput = document.getElementById('ratingInput');
    function paintStars(value) {
        stars.forEach(function (star) {
            const active = parseInt(star.dataset.value, 10) <= value;
            star.classList.toggle('fas', active);
            star.classList.toggle('far', !active);
        });
    }
    stars.forEach(function (star) {
        star.addEventListener('mouseenter', function () { paintStars(parseInt(star.dataset.value, 10)); });
        star.addEventListener('click', function () {
            ratingInput.value = star.dataset.value;
            paintStars(parseInt(star.dataset.value, 10));
        });
    });
    const starsWrapper = document.querySelector('.star-rating-input');
    if (starsWrapper) {
        starsWrapper.addEventListener('mouseleave', function () { paintStars(parseInt(ratingInput.value, 10)); });
    }
});
</script>
