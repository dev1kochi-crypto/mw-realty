@extends('portal.layouts.guest')

@section('title', 'Set Your Password')

@section('content')
<h5 class="fw-bold mb-1">Welcome, {{ $agent->name }}</h5>
<p class="text-muted small mb-3">
    {{ $agent->company?->displayName() ?? 'Your agency' }} added you as an agent. Choose a password to log in.
</p>

@if($errors->any())
<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ request()->fullUrl() }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" value="{{ $agent->email }}" disabled>
    </div>
    <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
        <div class="form-text">At least 8 characters, with letters and numbers.</div>
    </div>
    <div class="mb-3">
        <label class="form-label">Confirm password</label>
        <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-portal-primary w-100">Set password &amp; log in</button>
</form>
@endsection
