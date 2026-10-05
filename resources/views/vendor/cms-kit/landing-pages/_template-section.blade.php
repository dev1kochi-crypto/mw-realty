{{-- "Blog design" page type: cover image + publish date (the per-language content editor sits in the
     Title card, under the same language switcher — see _template-content-field). Rendered on the site
     with the blog detail page layout (BlogDetails.vue, via /api/landing-pages/{slug}). --}}
@php
    $page = $page ?? null;
    $coverUrl = $page && $page->feature_image ? media_url($page->feature_image) : null;
@endphp
<div class="card border-0 shadow-sm mb-4 template-only-field">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h6 class="fw-bold mb-0"><i class="fas fa-image text-primary me-2"></i>Cover &amp; Date</h6>
    </div>
    <div class="card-body p-4 pt-3">
        <div class="row g-4">
            <div class="col-md-7">
                <label class="form-label fw-bold">Cover Image</label>
                <input type="file" name="feature_image" class="form-control @error('feature_image') is-invalid @enderror" accept="image/*">
                @error('feature_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small class="text-muted">Shown at the top of the page, like a blog post's cover. Recommended 1600×900px.</small>
                @if($coverUrl)
                <div class="d-flex align-items-center gap-3 mt-2">
                    <img src="{{ $coverUrl }}" alt="" class="rounded" style="height: 64px; width: 110px; object-fit: cover;">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remove_feature_image" value="1" id="removeFeatureImage">
                        <label class="form-check-label small" for="removeFeatureImage">Remove cover image</label>
                    </div>
                </div>
                @endif
            </div>
            <div class="col-md-5">
                <label class="form-label fw-bold">Cover Image Alt Text</label>
                <input type="text" name="feature_image_alt" class="form-control" maxlength="255" value="{{ old('feature_image_alt', $page?->feature_image_alt) }}" placeholder="Describe the image">
                <label class="form-label fw-bold mt-3">Publish Date</label>
                <input type="date" name="published_at" class="form-control @error('published_at') is-invalid @enderror" value="{{ old('published_at', $page?->published_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

    </div>
</div>
