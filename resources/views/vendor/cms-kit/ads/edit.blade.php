@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.ads.index') }}">Ad Management</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Ad</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Edit Ad</h5>
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
        <form action="{{ route('cms.ads.update', $ad->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $ad->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Placement <span class="text-danger">*</span></label>
                    <small class="text-muted d-block mb-1">Which page on the site this ad shows on.</small>
                    <select name="placement" class="form-select @error('placement') is-invalid @enderror" required>
                        <option value="">Select a page&hellip;</option>
                        @foreach($pageOptions as $value => $label)
                        <option value="{{ $value }}" {{ old('placement', $ad->placement) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('placement')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-4">
                <div class="col-md-8">
                    <label class="form-label d-block">Image / GIF</label>
                    <small class="text-muted d-block mb-1">Recommended max: {{ $imageConfig['width'] ?? 4800 }}x{{ $imageConfig['height'] ?? 900 }}px, up to {{ $imageConfig['max_size'] ?? 8192 }}KB.</small>
                    <input type="file" name="image" accept="image/*" class="form-control @error('image') is-invalid @enderror">
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($ad->image)
                    <div class="mt-2">
                        <img src="{{ media_url($ad->image) }}" class="img-thumbnail" style="height: 70px;">
                    </div>
                    @endif
                    <input type="text" name="image_alt" class="form-control mt-2" placeholder="Image ALT text" value="{{ old('image_alt', $ad->image_alt) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Link URL</label>
                    <input type="url" name="link_url" class="form-control @error('link_url') is-invalid @enderror" value="{{ old('link_url', $ad->link_url) }}" placeholder="https://...">
                    @error('link_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-4">
                <div class="col-md-8">
                    <label class="form-label d-block">Mobile Image <span class="text-muted fw-normal">(optional)</span></label>
                    <small class="text-muted d-block mb-1">Shown on mobile screens instead of the image above — leave empty to use the same image on mobile. Recommended max: {{ $mobileImageConfig['width'] ?? 800 }}x{{ $mobileImageConfig['height'] ?? 400 }}px, up to {{ $mobileImageConfig['max_size'] ?? 4096 }}KB.</small>
                    <input type="file" name="mobile_image" accept="image/*" class="form-control @error('mobile_image') is-invalid @enderror">
                    @error('mobile_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($ad->mobile_image)
                    <div class="mt-2">
                        <img src="{{ media_url($ad->mobile_image) }}" class="img-thumbnail" style="height: 70px;">
                    </div>
                    @endif
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label">Starts</label>
                    <input type="date" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', $ad->starts_at?->format('Y-m-d')) }}">
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ends</label>
                    <input type="date" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', $ad->ends_at?->format('Y-m-d')) }}">
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $ad->order_index) }}" min="1">
                </div>
                <div class="col-md-3 d-flex align-items-end pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="status" id="adStatus" {{ old('status', $ad->status) ? 'checked' : '' }}>
                        <label class="form-check-label" for="adStatus">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                <a href="{{ route('cms.ads.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
