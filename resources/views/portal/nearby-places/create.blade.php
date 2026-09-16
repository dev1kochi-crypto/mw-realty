@extends('portal.layouts.app')

@section('title', 'Add Nearby Place')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <div class="portal-section-title mb-1">Add Nearby Place</div>
        <p class="text-muted small mb-0">Fill in the details below.</p>
    </div>
    <a href="{{ route('portal.nearby-places.index') }}" class="btn portal-btn-ghost btn-sm">Cancel</a>
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

<form action="{{ route('portal.nearby-places.store') }}" method="POST" id="nearbyPlaceForm">
    @csrf
    @include('portal.nearby-places._form')
</form>
@endsection

@push('scripts')
@include('portal.nearby-places._form-scripts')
@endpush
