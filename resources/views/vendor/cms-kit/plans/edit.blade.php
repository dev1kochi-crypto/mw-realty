@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.plans.index') }}">Plans</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Plan</li>
@endsection

@section('content')
<form action="{{ route('cms.plans.update', $plan->id) }}" method="POST">
    @csrf
    @method('PUT')
    @include('cms-kit::plans._form')
</form>
@endsection
