{{-- SEO of the calculator page — the same fields (and the same metadata row) as SEO › Metadata. --}}
<h6 class="fw-bold mb-1"><i class="fas fa-magnifying-glass me-2 text-primary"></i>SEO</h6>
<p class="text-muted small mb-3">How this page appears in search engines and when shared. Blank = the site default for this page. These are the same values as in SEO › Metadata.</p>

@php
    $code = $default;
    $seoValue = fn ($name) => old("seo.{$code}.{$name}", $seo?->{$name}[$code] ?? '');
@endphp
<div class="mb-4">
        <div class="row g-4">
            <div class="col-12">
                <label class="form-label fw-bold">Canonical URL</label>
                <input type="url" name="seo[{{ $code }}][canonical_url]" class="form-control @error("seo.{$code}.canonical_url") is-invalid @enderror" value="{{ $seoValue('canonical_url') }}" placeholder="https://example.com/page">
                @error("seo.{$code}.canonical_url") <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Optional. Full URL of the preferred version of this page for search engines.</div>
            </div>
            <div class="col-12">
                <label class="form-label d-flex justify-content-between fw-bold">
                    <span>Meta Title</span>
                    <span class="char-count text-muted small" data-target="meta_title_{{ $code }}">0/60</span>
                </label>
                <input type="text" id="meta_title_{{ $code }}" name="seo[{{ $code }}][meta_title]" class="form-control counter-input" data-max="60" maxlength="255" value="{{ $seoValue('meta_title') }}" placeholder="Page title for search engines (max 60 chars)">
            </div>
            <div class="col-12">
                <label class="form-label d-flex justify-content-between fw-bold">
                    <span>Meta Description</span>
                    <span class="char-count text-muted small" data-target="meta_description_{{ $code }}">0/160</span>
                </label>
                <textarea id="meta_description_{{ $code }}" name="seo[{{ $code }}][meta_description]" class="form-control counter-input" data-max="160" rows="3" placeholder="Page description for search results (max 160 chars)">{{ $seoValue('meta_description') }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Meta Keywords</label>
                <input type="text" name="seo[{{ $code }}][meta_keywords]" class="form-control" value="{{ $seoValue('meta_keywords') }}" placeholder="comma separated: keyword1, keyword2">
            </div>
            <div class="col-12">
                <div class="bg-light p-3 rounded-3">
                    <h6 class="fw-bold mb-3 text-secondary"><i class="fas fa-share-alt me-2"></i>Open Graph (Social Sharing)</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium small">OG Title</label>
                            <input type="text" name="seo[{{ $code }}][og_title]" class="form-control form-control-sm" value="{{ $seoValue('og_title') }}" placeholder="Title for social media sharing">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium small">OG Description</label>
                            <textarea name="seo[{{ $code }}][og_description]" class="form-control form-control-sm" rows="2" placeholder="Description for social media sharing">{{ $seoValue('og_description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Other Meta Tags</label>
                <textarea name="seo[{{ $code }}][other_meta_tags]" class="form-control" rows="2" placeholder='e.g. <meta name="robots" content="index, follow" />'>{{ $seoValue('other_meta_tags') }}</textarea>
                <div class="form-text text-muted small">Raw HTML tags to inject in &lt;head&gt;.</div>
            </div>
        </div>
</div>

<div class="row g-4">
    <div class="col-md-7">
        <label class="form-label fw-bold">Global OG Image</label>
        @if($seo?->og_image)
            <div class="mb-3">
                <img src="{{ media_url($seo->og_image) }}" class="img-thumbnail rounded shadow-sm" style="max-height: 120px;" alt="">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remove_og_image" id="removeCalcOgImage" value="1" {{ old('remove_og_image') ? 'checked' : '' }}>
                <label class="form-check-label" for="removeCalcOgImage">Remove current OG image</label>
            </div>
        @endif
        <input type="file" name="og_image" accept="image/*" class="form-control @error('og_image') is-invalid @enderror">
        @error('og_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="mt-2 text-muted small"><i class="fas fa-info-circle me-1"></i> Max size: 512 KB. Recommended: 1200x630px. Shown when the page is shared on social media (all languages).</div>
    </div>
</div>
