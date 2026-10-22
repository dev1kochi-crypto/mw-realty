@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.calculators.index') }}">Calculators</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit {{ $calculator->getTranslation('title') }}</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true) && $languages->count() > 1;
    $default = $languages->firstWhere('is_default', true)?->code ?? 'en';
    $faqRows = old('faqs') !== null
        ? array_values(old('faqs'))
        : $calculator->faqs->map(fn ($faq) => $faq->translations + ['status' => $faq->status])->all();
    $settings = $calculator->settings ?? [];
@endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Edit Calculator — {{ $calculator->getTranslation('title') }}</h5>
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

        <form action="{{ route('cms.calculators.update', $calculator->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" data-bs-toggle="tab" data-bs-target="#calc-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                @php
                    $code = $lang->code;
                    $t = $calculator->translations[$code] ?? [];
                    $field = fn ($name) => old("translations.{$code}.{$name}", $t[$name] ?? '');
                    $required = $code === $default;
                    $dir = $code === 'ar' ? 'rtl' : 'ltr';
                @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="calc-{{ $code }}" role="tabpanel">
                    <h6 class="fw-bold mb-1">Page</h6>
                    <p class="text-muted small mb-3">The title is used for the page banner, the breadcrumb and the Calculators menu; the heading and intro sit above the calculator.</p>
                    <div class="row g-4">
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Title @if($required)<span class="text-danger">*</span>@endif</label>
                            <input type="text" dir="{{ $dir }}" name="translations[{{ $code }}][title]" class="form-control" value="{{ $field('title') }}" @if($required) required @endif placeholder="e.g. Refinance Calculator">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Heading @if($required)<span class="text-danger">*</span>@endif</label>
                            <input type="text" dir="{{ $dir }}" name="translations[{{ $code }}][heading]" class="form-control" value="{{ $field('heading') }}" @if($required) required @endif>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Intro</label>
                            <textarea dir="{{ $dir }}" name="translations[{{ $code }}][lead]" class="form-control" rows="2">{{ $field('lead') }}</textarea>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-1">Enquiry form</h6>
                    <p class="text-muted small mb-3">The "talk to an advisor" form under the calculator. Leads from it arrive in Enquiries with the visitor's figures attached.</p>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Small label</label>
                            <input type="text" dir="{{ $dir }}" name="translations[{{ $code }}][form_eyebrow]" class="form-control" value="{{ $field('form_eyebrow') }}" placeholder="e.g. Free mortgage advice">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Form title</label>
                            <input type="text" dir="{{ $dir }}" name="translations[{{ $code }}][form_title]" class="form-control" value="{{ $field('form_title') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Text</label>
                            <textarea dir="{{ $dir }}" name="translations[{{ $code }}][form_text]" class="form-control" rows="4">{{ $field('form_text') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ticked points</label>
                            <textarea dir="{{ $dir }}" name="translations[{{ $code }}][form_points]" class="form-control" rows="4" placeholder="One point per line">{{ $field('form_points') }}</textarea>
                            <small class="text-muted">One point per line.</small>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">FAQ section title</label>
                            <input type="text" dir="{{ $dir }}" name="translations[{{ $code }}][faq_title]" class="form-control" value="{{ $field('faq_title') }}" placeholder="e.g. Refinancing FAQs">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            @if($calculator->key === 'refinance')
            <hr class="my-4">
            <h6 class="fw-bold mb-1">Refinance settings</h6>
            <p class="text-muted small mb-3">Interest rates follow the UAE banks' current refinance offers (EIBOR and each lender's rate), so they are not set here.</p>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Lenders</label>
                    <textarea name="settings[lenders]" class="form-control" rows="6" placeholder="One bank per line">{{ old('settings.lenders', implode("\n", $settings['lenders'] ?? [])) }}</textarea>
                    <small class="text-muted">The "Current mortgage lender" list. One bank per line.</small>
                </div>
            </div>
            @endif

            <hr class="my-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="fw-bold mb-1">FAQs</h6>
                    <p class="text-muted small mb-0">Shown under the calculator, in this order. A question needs both fields in {{ $languages->firstWhere('code', $default)?->name ?? 'the default language' }}; other languages fall back to it when left blank.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-faq><i class="fas fa-plus me-1"></i> Add question</button>
            </div>
            <div class="d-flex flex-column gap-2" data-faq-list>
                @foreach($faqRows as $i => $row)
                    @include('cms-kit::calculators._faq-row', ['index' => $i, 'row' => $row])
                @endforeach
            </div>

            <hr class="my-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="status" id="calcStatus" {{ old('status', $calculator->status) ? 'checked' : '' }}>
                <label class="form-check-label fw-bold" for="calcStatus">Show in the Calculators menu</label>
                <small class="text-muted d-block">When off, the calculator is left out of the header menu and the page tabs (its page still opens from a direct link).</small>
            </div>

            <hr class="my-4">
            @include('cms-kit::calculators._seo')

            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Update</button>
                <a href="{{ route('cms.calculators.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>

<template data-faq-template>
    @include('cms-kit::calculators._faq-row', ['index' => '__i__', 'row' => ['status' => true]])
</template>
@endsection

@push('scripts')
<script>
(function () {
    // SEO character counters (as on SEO › Metadata).
    function updateCount(input) {
        const count = document.querySelector('.char-count[data-target="' + input.id + '"]');
        if (!count) return;
        const len = input.value.length;
        count.textContent = len + '/' + input.dataset.max;
        count.classList.toggle('text-danger', len > Number(input.dataset.max));
        count.classList.toggle('text-muted', len <= Number(input.dataset.max));
    }
    document.querySelectorAll('.counter-input').forEach(function (input) {
        updateCount(input);
        input.addEventListener('input', function () { updateCount(input); });
    });

    // Rows are submitted in page order (the server re-indexes them), so moving a row reorders it.
    let next = 10000;
    document.querySelector('[data-add-faq]').addEventListener('click', function () {
        const html = document.querySelector('[data-faq-template]').innerHTML.replaceAll('__i__', next++);
        const list = document.querySelector('[data-faq-list]');
        list.insertAdjacentHTML('beforeend', html);
        list.lastElementChild.querySelector('input').focus();
    });
    document.addEventListener('click', function (e) {
        const action = e.target.closest('[data-row-action]');
        if (!action) return;
        const row = action.closest('[data-row]');
        if (action.dataset.rowAction === 'remove') row.remove();
        if (action.dataset.rowAction === 'up' && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
        if (action.dataset.rowAction === 'down' && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    });
})();
</script>
@endpush
