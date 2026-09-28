@extends('portal.layouts.app')

@section('title', 'Add ' . $itemLabel)

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <div class="portal-section-title mb-1">Add {{ $itemLabel }}</div>
        <p class="text-muted small mb-0">Fill in the details below — you can always come back and edit them later.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route($routePrefix . '.index') }}" class="btn portal-btn-ghost btn-sm">Cancel</a>
        <button type="submit" form="propertyForm" class="btn btn-portal-primary btn-sm px-4"><i class="fas fa-save me-1"></i> Save {{ $itemLabel }}</button>
    </div>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route($routePrefix . '.store') }}" method="POST" enctype="multipart/form-data" id="propertyForm">
    @csrf
    @include('portal.properties._form')
    <div class="mt-4 pt-2">
        <button type="submit" class="btn btn-portal-primary px-4">Save {{ $itemLabel }}</button>
        <a href="{{ route($routePrefix . '.index') }}" class="portal-btn-ghost btn">Cancel</a>
    </div>
</form>
@endsection
