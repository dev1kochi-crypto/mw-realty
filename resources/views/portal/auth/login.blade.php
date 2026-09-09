@extends('portal.layouts.guest')

@section('title', 'Log In')

@section('content')
<h1>Welcome back</h1>
<p class="subtitle">Log in to manage your properties and leads.</p>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('portal.login') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
    </div>

    <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="form-check mb-4">
        <input type="checkbox" name="remember" class="form-check-input" id="remember">
        <label class="form-check-label" for="remember" style="font-weight:500;">Remember me</label>
    </div>

    <button type="submit" class="btn btn-portal-primary w-100 mb-3">Log In</button>
    <p class="text-center text-muted" style="font-size:0.85rem;">
        New here? <a href="{{ route('portal.register') }}">Create an account</a>
    </p>
</form>
@endsection
