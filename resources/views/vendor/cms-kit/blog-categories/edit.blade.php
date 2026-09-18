@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.blogs.index') }}">Blogs</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cms.blog-categories.index') }}">Categories</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Category</li>
@endsection

@section('content')
@php $showLanguageUi = config('cms-kit.common.modules.languages', true); @endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Edit Blog Category</h5>
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
        <form action="{{ route('cms.blog-categories.update', $category->id) }}" method="POST">
            @csrf
            @method('PUT')

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="langTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $lang->code }}-content" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel">
                    <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $category->translations[$lang->code]['title'] ?? '') }}" required>
                    @error("translations.{$lang->code}.title")
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endforeach
            </div>

            <hr>

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $category->slug) }}">
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text mt-1">Changing this will not update blog posts already linked to the old slug.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $category->order_index) }}" min="1">
                </div>
                <div class="col-md-3 d-flex align-items-end pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="status" id="categoryStatus" {{ old('status', $category->status) ? 'checked' : '' }}>
                        <label class="form-check-label" for="categoryStatus">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Save</button>
                <a href="{{ route('cms.blog-categories.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
