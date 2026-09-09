@extends('portal.layouts.app')

@section('title', 'Edit Property')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">Edit Property</div>
    <a href="{{ route('portal.properties.index') }}" class="portal-btn-ghost btn btn-sm">Back to Properties</a>
</div>

<form action="{{ route('portal.properties.update', $property->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('portal.properties._form')
    <button type="submit" class="btn btn-portal-primary px-4">Update Property</button>
</form>
@endsection
