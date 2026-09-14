@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.landing-pages.index') }}">Landing Pages</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add Landing Page</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $fallbackLocale = config('app.fallback_locale');
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

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Add Landing Page</h4>
        <p class="text-muted mb-0 small">Write your own HTML, translate the text, and manage every image from one place.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('cms.landing-pages.index') }}" class="btn btn-outline-secondary js-cancel-btn">Cancel</a>
        <button type="submit" form="landingPageForm" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Save Landing Page</button>
    </div>
</div>

<form action="{{ route('cms.landing-pages.store') }}" method="POST" enctype="multipart/form-data" id="landingPageForm">
    @csrf
    {{-- Template (same as Blog) page type is disabled for now — see _page-type-picker note in edit.blade.php's history. --}}
    <input type="hidden" name="page_type" value="custom">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h6 class="fw-bold mb-0"><i class="fas fa-heading text-primary me-2"></i>Title</h6>
                </div>
                <div class="card-body p-4 pt-3">
                    @if($showLanguageUi)
                    <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="languageTabs" role="tablist">
                        @foreach($languages as $lang)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#panel-{{ $lang->code }}" type="button" role="tab">
                                <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                            </button>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    <div class="tab-content language-switcher-content">
                        @foreach($languages as $lang)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="panel-{{ $lang->code }}" role="tabpanel">
                            <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title") }}" required>
                            @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @include('cms-kit::landing-pages._custom-html-section', [
                'htmlValue' => old('custom_html'),
                'htmlError' => $errors->first('custom_html'),
                'cssValue' => old('custom_css'),
                'jsValue' => old('custom_js'),
                'existingTranslations' => old('text_translations', []),
            ])

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h6 class="fw-bold mb-3"><i class="fas fa-search text-primary me-2"></i>SEO &amp; Metadata</h6>
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#seoGeneral" type="button" role="tab">General</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#seoSocial" type="button" role="tab">Social Sharing</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#seoAdvanced" type="button" role="tab">Advanced</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="seoGeneral" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Meta Title</label>
                                    <input type="text" name="metadata[meta_title]" class="form-control" value="{{ old('metadata.meta_title') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Meta Description</label>
                                    <textarea name="metadata[meta_description]" class="form-control" rows="3">{{ old('metadata.meta_description') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Meta Keywords</label>
                                    <input type="text" name="metadata[meta_keywords]" class="form-control" value="{{ old('metadata.meta_keywords') }}" placeholder="comma separated: keyword1, keyword2">
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="seoSocial" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">OG Title</label>
                                    <input type="text" name="metadata[og_title]" class="form-control" value="{{ old('metadata.og_title') }}" placeholder="Title for social media sharing">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">OG Image</label>
                                    <input type="file" name="metadata[og_image]" class="form-control @error('metadata.og_image') is-invalid @enderror">
                                    @error('metadata.og_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <small class="text-muted">Recommended: 1200x630px.</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">OG Description</label>
                                    <textarea name="metadata[og_description]" class="form-control" rows="2" placeholder="Description for social media sharing">{{ old('metadata.og_description') }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="seoAdvanced" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Canonical URL</label>
                                    <input type="url" name="metadata[canonical_url]" class="form-control @error('metadata.canonical_url') is-invalid @enderror" value="{{ old('metadata.canonical_url') }}" placeholder="https://example.com/page">
                                    @error('metadata.canonical_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Other Meta Tags</label>
                                    <textarea name="metadata[other_meta_tags]" class="form-control" rows="3" placeholder='e.g. &lt;meta name="robots" content="index, follow" /&gt;'>{{ old('metadata.other_meta_tags') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="landing-page-sidebar">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0"><i class="fas fa-rocket text-primary me-2"></i>Publish</h6>
                    </div>
                    <div class="card-body p-4 pt-3">
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="status" id="pageStatus" value="1" {{ old('status', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="pageStatus">Active</label>
                        </div>
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $nextOrder) }}" min="1">
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0"><i class="fas fa-link text-primary me-2"></i>URL</h6>
                    </div>
                    <div class="card-body p-4 pt-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug') }}" placeholder="auto-generated from title">
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Leave empty to generate it from the title as you type.</small>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0"><i class="fas fa-window-maximize text-primary me-2"></i>Layout</h6>
                    </div>
                    <div class="card-body p-4 pt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="use_site_header_footer" id="useHeaderFooter" value="1" {{ old('use_site_header_footer', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="useHeaderFooter">Use the site's shared header &amp; footer</label>
                            <small class="text-muted d-block">Turn off for a fully standalone page — write your own header/footer inside the HTML if you still want one.</small>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0"><i class="fas fa-paper-plane text-primary me-2"></i>After a Form Submits</h6>
                    </div>
                    <div class="card-body p-4 pt-3">
                        <label class="form-label">Thank You URL (Optional)</label>
                        <input type="text" name="thank_you_url" class="form-control @error('thank_you_url') is-invalid @enderror" value="{{ old('thank_you_url') }}" placeholder="/thank-you or https://...">
                        @error('thank_you_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Redirects here after a successful form submission. Leave empty to show a plain "Thank you" banner on this same page instead.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-2 pt-2 pb-4">
        <button type="submit" class="btn btn-primary px-5">Save Landing Page</button>
        <a href="{{ route('cms.landing-pages.index') }}" class="btn btn-outline-secondary px-4 js-cancel-btn">Cancel</a>
    </div>
</form>
@endsection

@push('styles')
<style>
    .landing-page-sidebar { position: sticky; top: 1.5rem; }
    @media (max-width: 991.98px) {
        .landing-page-sidebar { position: static; }
    }
</style>
@endpush

@push('scripts')
{{-- TinyMCE isn't loaded — Custom HTML pages don't use it; re-add this if the Template page type is ever restored.
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
--}}
@include('cms-kit::landing-pages._form-scripts')
@endpush
