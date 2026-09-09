@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.plans.index') }}">Plans</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add Plan</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Add Plan</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('cms.plans.store') }}" method="POST">
            @csrf
            @include('cms-kit::plans._form')
            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Save</button>
                <a href="{{ route('cms.plans.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
