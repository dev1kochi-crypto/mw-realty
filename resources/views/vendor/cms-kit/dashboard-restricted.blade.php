@extends('cms-kit::layouts.cms')
@section('title', 'Dashboard')
@section('content')
<div class="card"><div class="card-body">
    <h1 class="h4">Welcome, {{ $cmsUser->name }}</h1>
    <p class="mb-0">Choose a section from the navigation to manage your permitted content.</p>
</div></div>
@endsection
