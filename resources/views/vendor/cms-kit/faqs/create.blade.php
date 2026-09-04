@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.faqs.index') }}">FAQs</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create FAQ</li>
@endsection

@section('content')
@php
    $faqConfig = config('cms-kit.database.faqs.items', []);
    $faqRequired = $faqConfig['required'] ?? [];
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
@endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Create New FAQ</h5>
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
        <form action="{{ route('cms.faqs.store') }}" method="POST">
            @csrf
            
            <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                <i class="fas fa-info-circle text-primary me-2"></i> 
                <strong>Note:</strong> Please ensure all required fields <span class="text-danger">(*)</span> are filled{{ $showLanguageUi ? ' across all language tabs' : '' }}.
            </div>

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="langTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content" id="langTabsContent">
                @foreach($languages as $lang)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}" role="tabpanel">
                    @if($faqConfig['question'] ?? true)
                    <div class="mb-3">
                        <label class="form-label fw-bold">Question{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }} {!! in_array('question', $faqRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                        <input type="text" name="translations[{{ $lang->code }}][question]" class="form-control @error("translations.{$lang->code}.question") is-invalid @enderror" value="{{ old("translations.{$lang->code}.question") }}" {{ in_array('question', $faqRequired) ? 'required' : '' }}>
                        @error("translations.{$lang->code}.question")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text mt-1 text-muted">Enter the question for this FAQ item.</div>
                    </div>
                    @endif
                    @if($faqConfig['answer'] ?? true)
                    <div class="mb-3">
                        <label class="form-label fw-bold">Answer{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }} {!! in_array('answer', $faqRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                        <textarea name="translations[{{ $lang->code }}][answer]" class="form-control tinymce-editor @error("translations.{$lang->code}.answer") is-invalid @enderror" rows="5">{{ old("translations.{$lang->code}.answer") }}</textarea>
                        @error("translations.{$lang->code}.answer")
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text mt-1 text-muted">Provide a detailed answer for the question above.</div>
                    </div>
                    @endif

                    @include('cms-kit::partials.extra-fields-translatable', [
                        'configKey' => 'faqs.items',
                        'lang' => $lang,
                        'existingTranslations' => [],
                    ])
                </div>
                @endforeach
            </div>

            <hr>

            <div class="row g-3">
                @if($faqConfig['order'] ?? true)
                <div class="col-md-4">
                    <label class="form-label">Order Index</label>
                    <input type="number" name="order_index" value="{{ old('order_index', $nextOrder) }}" class="form-control" min="1">
                </div>
                @endif
                @if($faqConfig['status'] ?? true)
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="status" checked id="statusSwitch">
                        <label class="form-check-label" for="statusSwitch">Active Status</label>
                    </div>
                </div>
                @endif
            </div>

            @include('cms-kit::partials.extra-fields-global', [
                'configKey' => 'faqs.items',
                'existingValues' => [],
            ])

            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-4">Save FAQ</button>
                <a href="{{ route('cms.faqs.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    tinymce.init({
        selector: '.tinymce-editor',
        height: 300,
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
        branding: false
    });

    document.addEventListener('invalid', function(e) {
        let invalidTabPane = e.target.closest('.tab-pane');
        if (invalidTabPane) {
            let tabId = invalidTabPane.id;
            let tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
            if (tabBtn && !tabBtn.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                setTimeout(() => { e.target.focus(); }, 150);
            }
        }
    }, true);
</script>
@endpush
