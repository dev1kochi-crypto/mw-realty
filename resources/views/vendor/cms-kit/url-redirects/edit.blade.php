@extends('cms-kit::layouts.cms')

@section('title', 'Edit redirect')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.url-redirects.index') }}">URL redirects</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="card glass-card border-0 shadow-sm">
    <div class="card-body p-4">
        <h5 class="fw-bold text-primary mb-4">Edit redirect</h5>
        <form action="{{ route('cms.url-redirects.update', $redirect) }}" method="POST">
            @csrf
            @method('PUT')
            @include('cms-kit::url-redirects._form', ['redirect' => $redirect])
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ route('cms.url-redirects.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
