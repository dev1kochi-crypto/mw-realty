<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $siteInfo->company_name ?? config('cms-kit.common.name', 'CMS Kit') }} - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @php
        $theme = config('cms-kit.common.theme', []);
        $primaryColor = $theme['primary_color'] ?? '#dc3545';
        $primaryGradient = $theme['primary_gradient'] ?? null;
        $primaryFill = $primaryGradient ?: $primaryColor;
        $normalizedPrimary = ltrim($primaryColor, '#');
        if (strlen($normalizedPrimary) === 3) {
            $normalizedPrimary = collect(str_split($normalizedPrimary))->map(fn ($char) => $char . $char)->implode('');
        }
        [$primaryRed, $primaryGreen, $primaryBlue] = sscanf($normalizedPrimary, '%02x%02x%02x') ?: [220, 53, 69];
    @endphp
    <style>
        :root {
            --primary-color: {{ $primaryColor }};
            --primary-gradient: {{ $primaryGradient ?: $primaryColor }};
            --primary-fill: {{ $primaryFill }};
            --heading-gradient: {{ $primaryFill }};
            --primary-rgb: {{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }};
            --bg-color: {{ $theme['background_color'] ?? '#f0f2f5' }};
            --theme-border-color: rgba({{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }}, 0.24);
            --theme-soft-bg: rgba({{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }}, 0.08);
            --theme-focus-ring: 0 0 0 0.2rem rgba({{ $primaryRed }}, {{ $primaryGreen }}, {{ $primaryBlue }}, 0.15);
        }
    </style>
    <link rel="stylesheet" href="{{ asset('vendor/cms-kit/css/cms-auth.css') }}">
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h3>{{ $siteInfo->company_name ?? config('cms-kit.common.name', 'CMS Kit') }}</h3>
        </div>

        <h5 class="mb-3">Reset Password</h5>
        <p class="text-muted small">Enter your email address and we'll send you a link to reset your password.</p>

        @if(session('status'))
            <div class="alert alert-success small">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger small">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('cms.password.email') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label small fw-bold">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Link</button>
            <div class="text-center">
                <a href="{{ route('cms.login') }}" class="small text-decoration-none" style="color: var(--primary-color)">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>
