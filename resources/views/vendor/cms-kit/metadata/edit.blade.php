@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.metadata.index') }}" class="text-decoration-none text-muted">Metadata</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="card glass-card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom">
        <h5 class="mb-0 fw-bold text-primary">Metadata: {{ $metadata->getTranslation('page_name', 'en') ?: ucfirst($metadata->page_key) }}</h5>
    </div>
    <div class="card-body p-4">
        <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
            <i class="fas fa-info-circle text-primary me-2"></i> 
            <strong>Note:</strong> Manage SEO settings{{ config('cms-kit.common.modules.languages', true) ? ' in English' : '' }}. Required fields are marked with <span class="text-danger">*</span>.
        </div>

        <form action="{{ route('cms.metadata.update', $metadata->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-4 mb-4">
                <div class="col-12">
                    <label class="form-label fw-bold">Canonical URL {!! in_array('canonical_url', config('cms-kit.database.metadata.required', [])) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="url" name="canonical_url[en]" class="form-control @error('canonical_url.en') is-invalid @enderror" value="{{ old('canonical_url.en', $metadata->canonical_url['en'] ?? '') }}" placeholder="https://example.com/page">
                    @error('canonical_url.en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text text-muted small">Optional. Full URL of the preferred version of this page for search engines.</div>
                </div>

                <div class="col-12">
                    <label class="form-label d-flex justify-content-between fw-bold">
                        <span>Meta Title {!! in_array('meta_title', config('cms-kit.database.metadata.required', [])) ? '<span class="text-danger">*</span>' : '' !!}</span>
                        <span class="char-count text-muted small" data-target="meta_title_en">0/60</span>
                    </label>
                    <input type="text" name="meta_title[en]" id="meta_title_en" class="form-control counter-input @error('meta_title.en') is-invalid @enderror" data-max="60" value="{{ old('meta_title.en', $metadata->meta_title['en'] ?? '') }}" placeholder="Page title for search engines (max 60 chars)">
                    @error('meta_title.en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label d-flex justify-content-between fw-bold">
                        <span>Meta Description {!! in_array('meta_description', config('cms-kit.database.metadata.required', [])) ? '<span class="text-danger">*</span>' : '' !!}</span>
                        <span class="char-count text-muted small" data-target="meta_description_en">0/160</span>
                    </label>
                    <textarea name="meta_description[en]" id="meta_description_en" class="form-control counter-input @error('meta_description.en') is-invalid @enderror" data-max="160" rows="3" placeholder="Page description for search results (max 160 chars)">{{ old('meta_description.en', $metadata->meta_description['en'] ?? '') }}</textarea>
                    @error('meta_description.en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Meta Keywords {!! in_array('meta_keywords', config('cms-kit.database.metadata.required', [])) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="text" name="meta_keywords[en]" class="form-control @error('meta_keywords.en') is-invalid @enderror" value="{{ old('meta_keywords.en', $metadata->meta_keywords['en'] ?? '') }}" placeholder="comma separated: keyword1, keyword2">
                    @error('meta_keywords.en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <div class="bg-light p-3 rounded-3 mt-2">
                        <h6 class="fw-bold mb-3 text-secondary"><i class="fas fa-share-alt me-2"></i>Open Graph (Social Sharing)</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-medium small">OG Title</label>
                                <input type="text" name="og_title[en]" class="form-control form-control-sm" value="{{ old('og_title.en', $metadata->og_title['en'] ?? '') }}" placeholder="Title for social media sharing">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium small">OG Description</label>
                                <textarea name="og_description[en]" class="form-control form-control-sm" rows="2" placeholder="Description for social media sharing">{{ old('og_description.en', $metadata->og_description['en'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Other Meta Tags</label>
                    <textarea name="other_meta_tags[en]" class="form-control" rows="2" placeholder='e.g. <meta name="robots" content="index, follow" />'>{{ old('other_meta_tags.en', $metadata->other_meta_tags['en'] ?? '') }}</textarea>
                    <div class="form-text text-muted small">Raw HTML tags to inject in &lt;head&gt;.</div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-4 align-items-center">
                <div class="col-md-7">
                    <label class="form-label fw-bold">Global OG Image</label>
                    @if($metadata->og_image)
                        <div class="mb-3">
                            <img src="{{ asset('storage/' . $metadata->og_image) }}" class="img-thumbnail rounded shadow-sm" style="max-height: 120px;">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remove_og_image" id="removeMetadataOgImage" value="1" {{ old('remove_og_image') ? 'checked' : '' }}>
                            <label class="form-check-label" for="removeMetadataOgImage">Remove current OG image</label>
                        </div>
                    @endif
                    <input type="file" name="og_image" class="form-control @error('og_image') is-invalid @enderror">
                    @error('og_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="mt-2 text-muted small">
                        <i class="fas fa-info-circle me-1"></i> Max size: 512 KB. Recommended: 1200x630px.
                    </div>
                </div>
                <div class="col-md-5 text-end pt-4">
                    <a href="{{ route('cms.metadata.index') }}" class="btn btn-outline-secondary px-4 me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary px-5">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function() {
        function updateCount($input) {
            const max = $input.data('max');
            const len = $input.val().length;
            const targetId = $input.attr('id');
            const $count = $(`.char-count[data-target="${targetId}"]`);
            
            if ($count.length) {
                $count.text(`${len}/${max}`);
                if (len > max) {
                    $count.removeClass('text-muted').addClass('text-danger');
                } else {
                    $count.removeClass('text-danger').addClass('text-muted');
                }
            }
        }

        $('.counter-input').on('input', function() {
            updateCount($(this));
        });

        // Initial count
        $('.counter-input').each(function() {
            updateCount($(this));
        });
    });
</script>
@endpush
