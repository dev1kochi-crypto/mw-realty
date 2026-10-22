@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.market-insights.index') }}">Market Insights</a></li>
    <li class="breadcrumb-item"><a href="{{ route($meta['route'] . '.index') }}">{{ $meta['plural'] }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $term->exists ? 'Edit' : 'Add' }} {{ $meta['label'] }}</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $fallback = config('app.fallback_locale', 'en');
@endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">{{ $term->exists ? 'Edit' : 'Add' }} Market Insight {{ $meta['label'] }}</h5>
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

        <form action="{{ $term->exists ? route($meta['route'] . '.update', $term->id) : route($meta['route'] . '.store') }}" method="POST">
            @csrf
            @if($term->exists) @method('PUT') @endif

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" data-bs-toggle="tab" data-bs-target="#term-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                @php $required = $lang->code === $fallback; @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="term-{{ $lang->code }}" role="tabpanel">
                    <label class="form-label fw-bold">Name {!! $required ? '<span class="text-danger">*</span>' : '' !!}</label>
                    <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror"
                           value="{{ old("translations.{$lang->code}.title", $term->translations[$lang->code]['title'] ?? '') }}"
                           placeholder="{{ $required ? $meta['example'] : '' }}" maxlength="100" dir="auto" {{ $required ? 'required' : '' }}>
                    @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @unless($required)
                        <div class="form-text">Leave empty to show the English name.</div>
                    @endunless
                </div>
                @endforeach
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Slug</label>
                    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $term->slug) }}" maxlength="50" placeholder="auto-generated from the English name">
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Used in the page URL filter (e.g. <code>?{{ $meta['type'] }}={{ $term->slug ?: 'price-trends' }}</code>).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $term->order_index) }}" min="1">
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check form-switch mt-md-4">
                        <input class="form-check-input" type="checkbox" name="status" id="termStatus" value="1" @checked(old('status', $term->status))>
                        <label class="form-check-label fw-bold" for="termStatus">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-5 border-top pt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-5">{{ $term->exists ? 'Update' : 'Save' }} {{ $meta['label'] }}</button>
                <a href="{{ route($meta['route'] . '.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
