@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.help-desk.index') }}">Help Desk</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit {{ $guide->title }}</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Edit Help Guide — {{ $guide->title }}</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('cms.help-desk.update', $guide->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('cms-kit::help-desk._form')
            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Update</button>
                <a href="{{ route('cms.help-desk.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
