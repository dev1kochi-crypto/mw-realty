@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.ads.index') }}">Ad Management</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add Ad</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Add Ad</h5>
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
        <form action="{{ route('cms.ads.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                    <small class="text-muted d-block mb-1">Internal label to identify this ad in the list — not shown publicly.</small>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Ramadan Sale Banner" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Placement <span class="text-danger">*</span></label>
                    <small class="text-muted d-block mb-1">Which page on the site this ad shows on.</small>
                    <select name="placement" class="form-select @error('placement') is-invalid @enderror" required>
                        <option value="">Select a page&hellip;</option>
                        @foreach($pageOptions as $value => $label)
                        <option value="{{ $value }}" {{ old('placement') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('placement')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-4">
                <div class="col-md-8">
                    <label class="form-label d-block">Image / GIF <span class="text-danger">*</span></label>
                    <small class="text-muted d-block mb-1">Recommended max: {{ $imageConfig['width'] ?? 4800 }}x{{ $imageConfig['height'] ?? 900 }}px, up to {{ $imageConfig['max_size'] ?? 8192 }}KB. Static images and animated GIFs are both supported.</small>
                    <input type="file" name="image" accept="image/*" class="form-control @error('image') is-invalid @enderror" required>
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <input type="text" name="image_alt" class="form-control mt-2" placeholder="Image ALT text" value="{{ old('image_alt') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Link URL</label>
                    <small class="text-muted d-block mb-1">Where clicking the ad takes visitors (optional).</small>
                    <input type="url" name="link_url" class="form-control @error('link_url') is-invalid @enderror" value="{{ old('link_url') }}" placeholder="https://...">
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
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label">Starts</label>
                    <input type="date" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at') }}">
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ends</label>
                    <input type="date" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at') }}">
                    <small class="text-muted">Leave both dates blank to run indefinitely.</small>
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $nextOrder) }}" min="1">
                </div>
                <div class="col-md-3 d-flex align-items-end pb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="status" id="adStatus" checked>
                        <label class="form-check-label" for="adStatus">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Save</button>
                <a href="{{ route('cms.ads.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
