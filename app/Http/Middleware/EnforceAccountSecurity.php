<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforceAccountSecurity
{
    public function handle(Request $request, Closure $next)
    {
        foreach (['cms', 'portal'] as $guard) {
            $user = Auth::guard($guard)->user();
            if (!$user) {
                continue;
            }
            $key = 'password_hash_'.$guard;
            $fingerprint = $request->session()->get($key);
            if (!$fingerprint && Auth::guard($guard)->viaRemember()) {
                $request->session()->put($key, $fingerprint = $user->getAuthPassword());
            }
            if (!$user->is_active || !is_string($fingerprint) || !hash_equals($user->getAuthPassword(), $fingerprint)) {
                Auth::guard($guard)->logout();
                $request->session()->forget($key);
                $request->session()->regenerate();
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Your session has expired. Please sign in again.'], 401);
                }
                return redirect()->route($guard === 'cms' ? 'cms.login' : 'portal.login')
                    ->withErrors(['email' => 'Please sign in again. Disabled accounts cannot sign in.']);
            }
        }

        return $next($request);
    }
}
