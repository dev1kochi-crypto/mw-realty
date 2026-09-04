@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.blogs.index') }}">Blogs</a></li>
    <li class="breadcrumb-item active">Add Blog</li>
@endsection

@section('content')
@php
    $blogConfig = config('cms-kit.database.blogs.items', []);
    $blogRequired = $blogConfig['required'] ?? [];
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $blogImageLabels = [
        'feature_image' => 'Feature Image (Listing)',
        'detail_image' => 'Detail Image (Hero)',
        'banner_image' => 'Banner Image',
        'image_3' => 'Image 3',
        'image_4' => 'Image 4',
    ];
@endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Add New Blog Post</h5>
    </div>
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form action="{{ route('cms.blogs.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="row mb-4">
                @if($blogConfig['slug'] ?? true)
                <div class="col-md-6">
                    <label class="form-label">Slug (Optional)</label>
                    <input type="text" name="slug" class="form-control" placeholder="auto-generated if empty">
                </div>
                @endif
                @if($blogConfig['published_at'] ?? true)
                <div class="col-md-6">
                    <label class="form-label">Published Date {!! in_array('published_at', $blogRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="date" name="published_at" class="form-control" value="{{ date('Y-m-d') }}" {{ in_array('published_at', $blogRequired) ? 'required' : '' }}>
                </div>
                @endif
            </div>

            <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                <i class="fas fa-info-circle text-primary me-2"></i> 
                <strong>Note:</strong> Please ensure all required fields <span class="text-danger">(*)</span> are filled{{ $showLanguageUi ? ' across all language tabs' : '' }}.
            </div>

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

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="panel-{{ $lang->code }}" role="tabpanel">
                    <div class="row g-4">
                        @if($blogConfig['title'] ?? true)
                        <div class="col-12">
                            <label class="form-label fw-bold">Blog Title {!! in_array('title', $blogRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title") }}" {{ in_array('title', $blogRequired) ? 'required' : '' }}>
                            @error("translations.{$lang->code}.title")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                        @if($blogConfig['content'] ?? true)
                        <div class="col-12">
                            <label class="form-label fw-bold">Content {!! in_array('content', $blogRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <textarea name="translations[{{ $lang->code }}][content]" class="form-control tinymce-editor @error("translations.{$lang->code}.content") is-invalid @enderror" rows="10">{{ old("translations.{$lang->code}.content") }}</textarea>
                            @error("translations.{$lang->code}.content")
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @include('cms-kit::partials.extra-fields-translatable', [
                            'configKey' => 'blogs.items',
                            'lang' => $lang,
                            'existingTranslations' => [],
                        ])
                    </div>
                </div>
                @endforeach
            </div>

            <div class="card bg-light border-0 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Images</h6>
                    <div class="row g-4">
                        @foreach($blogImageLabels as $field => $label)
                            @if($blogConfig[$field] ?? true)
                            <div class="col-md-6">
                                <label class="form-label">{{ $label }} {!! in_array($field, $blogRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                <input type="file" name="{{ $field }}" class="form-control" {{ in_array($field, $blogRequired) ? 'required' : '' }}>
                                <small class="text-muted">Recommended: {{ $imagesConfig[$field]['width'] }}x{{ $imagesConfig[$field]['height'] }}px</small>
                            </div>
                            @endif
                            @php $altField = $field === 'banner_image' ? 'banner_alt' : $field . '_alt'; @endphp
                            @if($blogConfig[$altField] ?? true)
                            <div class="col-md-6">
                                <label class="form-label">{{ $label }} ALT text</label>
                                <input type="text" name="{{ $altField }}" class="form-control">
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card bg-light border-0 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-search me-2"></i>SEO & Metadata</h6>
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Meta Title</label>
                            <input type="text" name="metadata[meta_title]" class="form-control" placeholder="Page title for search engines">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Meta Description</label>
                            <textarea name="metadata[meta_description]" class="form-control" rows="3" placeholder="Page description for search results"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Meta Keywords</label>
                            <input type="text" name="metadata[meta_keywords]" class="form-control" placeholder="keyword1, keyword2, ...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Canonical URL</label>
                            <input type="url" name="metadata[canonical_url]" class="form-control" placeholder="https://example.com/blog/post-slug">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">OG Title</label>
                            <input type="text" name="metadata[og_title]" class="form-control" placeholder="Title for social sharing">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">OG Description</label>
                            <textarea name="metadata[og_description]" class="form-control" rows="2" placeholder="Description for social sharing"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">OG Image</label>
                            <input type="file" name="metadata[og_image]" class="form-control">
                            <small class="text-muted">Recommended: 1200x630px. Max: 512KB</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Other Meta Tags</label>
                            <textarea name="metadata[other_meta_tags]" class="form-control" rows="3" placeholder='e.g. <meta name="robots" content="index, follow" />'></textarea>
                            <small class="text-muted">Raw HTML tags to be included in the header.</small>
                        </div>
                    </div>
                </div>
            </div>

            @include('cms-kit::partials.extra-fields-global', [
                'configKey' => 'blogs.items',
                'existingValues' => [],
            ])

            <div class="card bg-light border-0 mb-4">
                <div class="card-body p-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="status" id="blogStatus" value="1" {{ old('status', true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="blogStatus">Status (Active)</label>
                    </div>
                </div>
            </div>

            <div class="mt-5 border-top pt-4">
                <button type="submit" class="btn btn-primary px-5">Save Blog Post</button>
                <a href="{{ route('cms.blogs.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/site-manager/js/tinymce/tinymce.min.js') }}"></script>
<script>
    tinymce.init({
        selector: '.tinymce-editor',
        height: 400,
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
    });

    document.addEventListener('invalid', function(e) {
        const invalidTabPane = e.target.closest('.tab-pane');
        if (invalidTabPane) {
            const tabId = invalidTabPane.id;
            const tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
            if (tabBtn && !tabBtn.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                setTimeout(() => { e.target.focus(); }, 150);
            }
        }
    }, true);
</script>
@endpush
