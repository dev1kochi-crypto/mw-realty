@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.filters.index') }}">Filters</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cms.find-properties.index') }}">Find Properties</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Card</li>
@endsection

@section('content')
@php $showLanguageUi = config('cms-kit.common.modules.languages', true); @endphp
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Edit Card</h5>
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
        <div class="alert alert-info py-2 px-3 mb-4" style="font-size: 0.85rem;">
            <i class="fas fa-chart-bar me-1"></i> Currently linked to <strong>{{ $item->propertyCount() }}</strong> active {{ \Illuminate\Support\Str::plural('property', $item->propertyCount()) }}.
        </div>
        <form action="{{ route('cms.find-properties.update', $item->id) }}" method="POST" enctype="multipart/form-data">
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
                @php $t = $item->translations[$lang->code] ?? []; @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel">
                    <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $t['title'] ?? '') }}" required>
                    @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @endforeach
            </div>

            <hr>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Property Type</label>
                    @if($propertyTypeFilter && $propertyTypeFilter->activeValues->count())
                        <select name="property_type" class="form-select">
                            <option value="">-- Select --</option>
                            @foreach($propertyTypeFilter->activeValues as $option)
                            <option value="{{ $option->value }}" {{ old('property_type', $item->property_type) == $option->value ? 'selected' : '' }}>{{ $option->getTranslation('label') }}</option>
                            @endforeach
                        </select>
                        <div class="form-text mt-1 text-muted">This card links to properties filtered by this type, and the count shown is calculated automatically.</div>
                    @else
                        <input type="text" name="property_type" class="form-control" value="{{ old('property_type', $item->property_type) }}" placeholder="No property type options configured yet — add one under Filters &raquo; Property Type">
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label d-block">Image</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $iconConfig['width'] }}x{{ $iconConfig['height'] }}px, Max: {{ $iconConfig['max_size'] }}KB</small>
                    <input type="file" name="image" class="form-control @error('image') is-invalid @enderror">
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <input type="text" name="image_alt" class="form-control mt-2" placeholder="Image ALT text" value="{{ old('image_alt', $item->image_alt) }}">
                    @if($item->image)
                        <div class="mt-2">
                            <img src="{{ media_url($item->image) }}" class="img-thumbnail" style="height: 60px;">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage" value="1">
                                <label class="form-check-label" for="removeImage">Remove current image</label>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $item->order_index) }}" min="1">
                </div>
                <div class="col-md-3 d-flex align-items-end pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="status" id="itemStatus" {{ old('status', $item->status) ? 'checked' : '' }}>
                        <label class="form-check-label" for="itemStatus">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Update</button>
                <a href="{{ route('cms.find-properties.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
