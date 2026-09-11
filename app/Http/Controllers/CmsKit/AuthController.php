<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (\Illuminate\Support\Facades\Auth::guard('cms')->check()) {
            return redirect()->route('cms.testimonials.index');
        }
        return view('cms-kit::auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('cms')->attempt(array_merge($request->only('email', 'password'), ['is_active' => true]), $request->filled('remember'))) {
            $request->session()->regenerate();
            $request->session()->put('password_hash_cms', Auth::guard('cms')->user()->getAuthPassword());
            return redirect()->intended(route('cms.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::guard('cms')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('cms.login');
    }
}


