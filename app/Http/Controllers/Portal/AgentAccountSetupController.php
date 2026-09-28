<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Set-password page for an agent account an agency created — linked from the approval email
 * (AgencyMembershipService::setupUrl()). The link is signed + 7-day, and carries a fingerprint of
 * the current password hash, so it stops working the moment a password is set.
 */
class AgentAccountSetupController extends Controller
{
    protected function resolve(Request $request, $agentId): PortalUser
    {
        $agent = PortalUser::where('type', 'agent')->findOrFail($agentId);
        abort_unless(
            hash_equals(substr(hash('sha256', (string) $agent->password), 0, 16), (string) $request->query('v')),
            403,
            'This link has already been used. Log in, or ask your agency to resend it.'
        );
        abort_unless($agent->is_active && $agent->isApproved(), 403, 'This account is not active.');

        return $agent;
    }

    public function show(Request $request, $agent)
    {
        return view('portal.auth.agent-setup', ['agent' => $this->resolve($request, $agent)]);
    }

    public function store(Request $request, $agent)
    {
        $agent = $this->resolve($request, $agent);
        $request->validate(['password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);

        $agent->forceFill(['password' => Hash::make($request->input('password'))])->save();

        Auth::guard('portal')->login($agent);
        $request->session()->regenerate();
        $request->session()->put('password_hash_portal', $agent->getAuthPassword());

        return redirect()->route('portal.dashboard')->with('success', 'Password set — welcome to your agency portal.');
    }
}
