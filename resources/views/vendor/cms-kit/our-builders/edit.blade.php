@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.our-builders.index') }}">Our Builders</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Builder</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Edit Builder</h5>
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
        <form action="{{ route('cms.our-builders.update', $item->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label d-block">Logo</label>
                    <small class="text-muted d-block mb-1">Recommended size: {{ $imageConfig['width'] }}x{{ $imageConfig['height'] }}px, Max: {{ $imageConfig['max_size'] }}KB</small>
                    <input type="file" name="image" class="form-control">
                    <input type="text" name="image_alt" class="form-control mt-2" placeholder="Logo ALT text" value="{{ old('image_alt', $item->image_alt) }}">
                    @if($item->image)
                        <div class="mt-2">
                            <img src="{{ media_url($item->image) }}" class="img-thumbnail" style="height: 60px;">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage" value="1">
                                <label class="form-check-label" for="removeImage">Remove current logo</label>
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
                <a href="{{ route('cms.our-builders.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
