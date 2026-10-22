@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $fallback = config('app.fallback_locale', 'en');
    $stats = old('stats', $insight->stats ?? []);
    $meta = $insight->metadata ?? [];
    $trendLabels = ['up' => '▲ Up', 'down' => '▼ Down', 'flat' => '● No change'];
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card bg-light border-0 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-calendar-alt me-2"></i>Publishing</h6>
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Published Date <span class="text-danger">*</span></label>
                <input type="date" name="published_at" class="form-control @error('published_at') is-invalid @enderror" value="{{ old('published_at', optional($insight->published_at)->format('Y-m-d')) }}" required>
                @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Slug</label>
                <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $insight->slug) }}" placeholder="auto-generated from title">
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
    <i class="fas fa-info-circle text-primary me-2"></i>
    <strong>Note:</strong> English is required; any other language left empty falls back to the English text on the website.
</div>

@if($showLanguageUi)
<ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" role="tablist">
    @foreach($languages as $lang)
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" data-bs-toggle="tab" data-bs-target="#insight-panel-{{ $lang->code }}" type="button" role="tab">
            <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
        </button>
    </li>
    @endforeach
</ul>
@endif

<div class="tab-content mb-4 language-switcher-content">
    {{-- Shared across languages: sits outside the tab panes so it stays visible on every tab. --}}
    <div class="row g-4">
        <div class="col-md-6">
            <label class="form-label fw-bold">Topic <span class="text-danger">*</span></label>
            <select name="topic" class="form-select @error('topic') is-invalid @enderror" required>
                <option value="">-- Select --</option>
                @foreach($topics as $key => $label)
                    <option value="{{ $key }}" @selected(old('topic', $insight->topic) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('topic')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold">Region</label>
            <select name="region" class="form-select @error('region') is-invalid @enderror">
                <option value="">-- None --</option>
                @foreach($regions as $key => $label)
                    <option value="{{ $key }}" @selected(old('region', $insight->region) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('region')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="form-text mt-2">
        Need another option? Add it under
        <a href="{{ route('cms.market-insight-topics.index') }}" target="_blank">Market Insights &rsaquo; Topics</a> or
        <a href="{{ route('cms.market-insight-regions.index') }}" target="_blank">Market Insights &rsaquo; Regions</a>, then reload this page.
    </div>
    <hr class="my-4">

    @foreach($languages as $lang)
    @php $required = $lang->code === $fallback; @endphp
    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="insight-panel-{{ $lang->code }}" role="tabpanel">
        <div class="row g-4">
            <div class="col-12">
                <label class="form-label fw-bold">Title {!! $required ? '<span class="text-danger">*</span>' : '' !!}</label>
                <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $insight->translations[$lang->code]['title'] ?? '') }}" {{ $required ? 'required' : '' }} maxlength="255">
                @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Author Name</label>
                <input type="text" name="translations[{{ $lang->code }}][author_name]" class="form-control" value="{{ old("translations.{$lang->code}.author_name", $insight->translations[$lang->code]['author_name'] ?? '') }}" maxlength="100" placeholder="{{ $required ? 'e.g. MW Realty Research' : '' }}" dir="auto">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Author Role</label>
                <input type="text" name="translations[{{ $lang->code }}][author_role]" class="form-control" value="{{ old("translations.{$lang->code}.author_role", $insight->translations[$lang->code]['author_role'] ?? '') }}" maxlength="100" placeholder="{{ $required ? 'e.g. Market Research Analyst' : '' }}" dir="auto">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Summary {!! $required ? '<span class="text-danger">*</span>' : '' !!}</label>
                <textarea name="translations[{{ $lang->code }}][summary]" class="form-control @error("translations.{$lang->code}.summary") is-invalid @enderror" rows="5" maxlength="500" {{ $required ? 'required' : '' }} placeholder="One or two sentences shown on the listing card and at the top of the article.">{{ old("translations.{$lang->code}.summary", $insight->translations[$lang->code]['summary'] ?? '') }}</textarea>
                @error("translations.{$lang->code}.summary")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Key Takeaways</label>
                <textarea name="translations[{{ $lang->code }}][takeaways]" class="form-control" rows="5" placeholder="One takeaway per line, e.g.&#10;Transactions up 11% quarter on quarter&#10;Villa prices outpacing apartments">{{ old("translations.{$lang->code}.takeaways", $insight->translations[$lang->code]['takeaways'] ?? '') }}</textarea>
                <div class="form-text">One per line — shown as a highlighted "Key Takeaways" box above the article.</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Article {!! $required ? '<span class="text-danger">*</span>' : '' !!}</label>
                <textarea name="translations[{{ $lang->code }}][content]" class="form-control tinymce-editor @error("translations.{$lang->code}.content") is-invalid @enderror" rows="12">{{ old("translations.{$lang->code}.content", $insight->translations[$lang->code]['content'] ?? '') }}</textarea>
                @error("translations.{$lang->code}.content")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card bg-light border-0 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2"></i>Key Figures</h6>
        <p class="text-muted small mb-3">Up to {{ $maxStats }} headline numbers shown as stat cards (e.g. <strong>+11%</strong> "Transactions QoQ"). Leave a row's value empty to skip it.</p>
        @for($i = 0; $i < $maxStats; $i++)
        <div class="row g-3 align-items-end {{ $i ? 'mt-1 pt-3 border-top' : '' }}">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Value</label>
                <input type="text" name="stats[{{ $i }}][value]" class="form-control" value="{{ $stats[$i]['value'] ?? '' }}" maxlength="20" placeholder="+11%">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Trend</label>
                <select name="stats[{{ $i }}][trend]" class="form-select">
                    @foreach($trends as $trend)
                        <option value="{{ $trend }}" @selected(($stats[$i]['trend'] ?? 'up') === $trend)>{{ $trendLabels[$trend] }}</option>
                    @endforeach
                </select>
            </div>
            @foreach($languages as $lang)
            <div class="col">
                <label class="form-label small fw-bold">Label ({{ $lang->name }})</label>
                <input type="text" name="stats[{{ $i }}][label][{{ $lang->code }}]" class="form-control" value="{{ $stats[$i]['label'][$lang->code] ?? '' }}" maxlength="60" placeholder="{{ $lang->code === $fallback ? 'Transactions QoQ' : '' }}" dir="auto">
            </div>
            @endforeach
        </div>
        @endfor
    </div>
</div>

<div class="card bg-light border-0 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-1"><i class="fas fa-image me-2"></i>Images &amp; Report</h6>
        <p class="text-muted small mb-3">Upload each image at its own size so it stays sharp where it's shown. Max {{ (int) (config('cms-kit.database.market_insights.image_max_kb', 4096) / 1024) }} MB each.</p>
        <div class="row g-4">
            @foreach([
                'card_image' => ['Card Image', 'Listing grid and "Related Insights" cards.', '1200 × 750 px (16:10)', true],
                'detail_image' => ['Article Image', 'Large banner at the top of the article page.', '1920 × 1080 px (16:9)', true],
                'featured_image' => ['Featured Image', 'Large card at the top of the listing when this post is Featured. Falls back to the article image.', '1600 × 1100 px', false],
            ] as $field => [$label, $help, $size, $required])
            <div class="col-md-4">
                <label class="form-label fw-bold">{{ $label }} {!! $required && !$insight->{$field} ? '<span class="text-danger">*</span>' : '' !!}</label>
                @if($insight->{$field})
                    <div class="mb-2 d-flex align-items-end gap-3">
                        <img src="{{ media_url($insight->{$field}) }}" alt="" class="img-thumbnail" style="height: 90px; width: 140px; object-fit: cover;">
                        @unless($required)
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remove_{{ $field }}" id="remove_{{ $field }}" value="1">
                            <label class="form-check-label small" for="remove_{{ $field }}">Remove</label>
                        </div>
                        @endunless
                    </div>
                @endif
                <input type="file" name="{{ $field }}" accept="image/*" class="form-control @error($field) is-invalid @enderror" {{ $required && !$insight->{$field} ? 'required' : '' }}>
                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small class="text-muted d-block mt-1">{{ $help }}<br>Recommended: <strong>{{ $size }}</strong></small>
            </div>
            @endforeach
            <div class="col-md-6">
                <label class="form-label fw-bold">Image ALT text</label>
                <input type="text" name="image_alt" class="form-control" value="{{ old('image_alt', $insight->image_alt) }}" maxlength="255" placeholder="e.g. Dubai skyline at dusk">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Downloadable Report (PDF)</label>
                @if($insight->report_file)
                    <div class="mb-2 d-flex align-items-center gap-3">
                        <a href="{{ media_url($insight->report_file) }}" target="_blank" rel="noopener"><i class="fas fa-file-pdf text-danger me-1"></i>Current report</a>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remove_report_file" id="removeReportFile" value="1">
                            <label class="form-check-label small" for="removeReportFile">Remove</label>
                        </div>
                    </div>
                @endif
                <input type="file" name="report_file" accept="application/pdf" class="form-control @error('report_file') is-invalid @enderror">
                @error('report_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small class="text-muted">Optional. Adds a "Download full report" button. Max {{ (int) (config('cms-kit.database.market_insights.report_file_max_kb', 10240) / 1024) }} MB.</small>
            </div>
        </div>
    </div>
</div>

<div class="card bg-light border-0 mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-search me-2"></i>SEO &amp; Metadata</h6>
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Meta Title</label>
                <input type="text" name="metadata[meta_title]" class="form-control" value="{{ old('metadata.meta_title', $meta['meta_title'] ?? '') }}" placeholder="Defaults to the post title">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Meta Keywords</label>
                <input type="text" name="metadata[meta_keywords]" class="form-control" value="{{ old('metadata.meta_keywords', $meta['meta_keywords'] ?? '') }}" placeholder="keyword1, keyword2">
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Meta Description</label>
                <textarea name="metadata[meta_description]" class="form-control" rows="2" placeholder="Defaults to the summary">{{ old('metadata.meta_description', $meta['meta_description'] ?? '') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">OG Title</label>
                <input type="text" name="metadata[og_title]" class="form-control" value="{{ old('metadata.og_title', $meta['og_title'] ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Canonical URL</label>
                <input type="url" name="metadata[canonical_url]" class="form-control" value="{{ old('metadata.canonical_url', $meta['canonical_url'] ?? '') }}" placeholder="https://example.com/market-insights/post-slug">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">OG Description</label>
                <textarea name="metadata[og_description]" class="form-control" rows="2">{{ old('metadata.og_description', $meta['og_description'] ?? '') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">OG Image</label>
                @if(!empty($meta['og_image']))
                    <div class="mb-2 d-flex align-items-center gap-3">
                        <img src="{{ media_url($meta['og_image']) }}" alt="" class="img-thumbnail" style="max-height: 60px;">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remove_metadata_og_image" id="removeOgImage" value="1">
                            <label class="form-check-label small" for="removeOgImage">Remove</label>
                        </div>
                    </div>
                @endif
                <input type="file" name="metadata[og_image]" accept="image/*" class="form-control">
                <small class="text-muted">Defaults to the article image. Recommended 1200×630px.</small>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Other Meta Tags</label>
                <textarea name="metadata[other_meta_tags]" class="form-control" rows="2" placeholder='e.g. <meta name="robots" content="index, follow" />'>{{ old('metadata.other_meta_tags', $meta['other_meta_tags'] ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card bg-light border-0 mb-4">
    <div class="card-body p-4">
        <div class="row g-4 align-items-center">
            <div class="col-md-3">
                <label class="form-label fw-bold">Order</label>
                <input type="number" name="order_index" class="form-control" min="1" value="{{ old('order_index', $insight->order_index) }}" placeholder="1 = first">
            </div>
            <div class="col-md-auto">
                <div class="form-check form-switch mt-md-4">
                    <input class="form-check-input" type="checkbox" name="is_featured" id="insightFeatured" value="1" @checked(old('is_featured', $insight->is_featured))>
                    <label class="form-check-label fw-bold" for="insightFeatured">Featured</label>
                </div>
                <div class="form-text">The newest featured post is shown large at the top of the page.</div>
            </div>
            <div class="col-md-auto">
                <div class="form-check form-switch mt-md-4">
                    <input class="form-check-input" type="checkbox" name="status" id="insightStatus" value="1" @checked(old('status', $insight->status ?? true))>
                    <label class="form-check-label fw-bold" for="insightStatus">Status (Active)</label>
                </div>
                <div class="form-text">&nbsp;</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/site-manager/js/tinymce/tinymce.min.js') }}"></script>
<script>
    tinymce.init({
        selector: '.tinymce-editor',
        height: 420,
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table | removeformat | help'
    });

    // Jump to the language tab holding the first invalid required field.
    document.addEventListener('invalid', function (e) {
        const pane = e.target.closest('.tab-pane');
        if (!pane) return;
        const tabBtn = document.querySelector(`[data-bs-target="#${pane.id}"]`);
        if (tabBtn && !tabBtn.classList.contains('active')) {
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            setTimeout(() => e.target.focus(), 150);
        }
    }, true);
</script>
@endpush
