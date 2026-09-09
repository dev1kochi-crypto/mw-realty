<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PortalAuthController extends Controller
{
    public function showRegister()
    {
        return view('portal.auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'type' => ['required', Rule::in(['company', 'agent'])],
            'name' => 'required|string|max:255',
            'company_name' => 'required_if:type,company|nullable|string|max:255',
            'email' => 'required|email|unique:portal_users,email',
            'phone' => 'nullable|string|max:50',
            'license_no' => 'nullable|string|max:100',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        PortalUser::create([
            'type' => $request->input('type'),
            'name' => $request->input('name'),
            'company_name' => $request->input('type') === 'company' ? $request->input('company_name') : null,
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'license_no' => $request->input('license_no'),
            'password' => Hash::make($request->input('password')),
            'status' => 'pending',
        ]);

        return redirect()->route('portal.login')
            ->with('success', 'Account created. It is now pending Super Admin approval — you\'ll be able to log in once approved.');
    }

    public function showLogin()
    {
        return view('portal.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $portalUser = PortalUser::where('email', $request->input('email'))->first();

        if (!$portalUser || !Hash::check($request->input('password'), $portalUser->password)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        if ($portalUser->status === 'pending') {
            return back()->withErrors(['email' => 'Your account is still pending Super Admin approval.'])->onlyInput('email');
        }

        if ($portalUser->status === 'rejected') {
            return back()->withErrors(['email' => 'Your account request was rejected. Contact the site administrator.'])->onlyInput('email');
        }

        Auth::guard('portal')->login($portalUser, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
