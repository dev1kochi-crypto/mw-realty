<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use App\Services\TwoFactor\PortalDeviceTrust;
use App\Services\TwoFactor\Totp;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authenticator-app 2FA (Authy, Google Authenticator, ...) for the logged-in Agent/Company:
 * set-up page (also the step right after sign-up), recovery codes, disabling, and a company's
 * "mandatory for my company" switch. The login-time challenge lives in PortalAuthController.
 */
class PortalTwoFactorController extends Controller
{
    private const PENDING_SECRET_KEY = 'portal_2fa_pending_secret';
    private const RECONFIGURE_KEY = 'portal_2fa_reconfigure';
    private const ENFORCE_AFTER_SETUP_KEY = 'portal_2fa_enforce_after_setup';
    private const ISSUER = 'MW Realty';

    public function __construct(private Totp $totp)
    {
    }

    /** Security page (account menu → Security): 2FA status and actions, company switch, devices. */
    public function index(Request $request)
    {
        $portalUser = $this->portalUser();
        // Back here without finishing set-up = the "make it mandatory" request was abandoned.
        $request->session()->forget(self::ENFORCE_AFTER_SETUP_KEY);

        return view('portal.security.index', [
            'portalUser' => $portalUser,
            'trustedDevices' => \Illuminate\Support\Facades\DB::table('portal_trusted_devices')
                ->where('portal_user_id', $portalUser->id)
                ->orderByDesc('last_used_at')
                ->get(['user_agent', 'ip_address', 'last_used_at']),
        ]);
    }

    public function setup(Request $request)
    {
        $portalUser = $this->portalUser();
        $recoveryCodes = $request->session()->get('two_factor_recovery_codes');
        $reconfiguring = $portalUser->hasTwoFactorEnabled() && $request->session()->get(self::RECONFIGURE_KEY);

        // Already on — the page only shows the freshly issued recovery codes, or a new phone's QR.
        if ($portalUser->hasTwoFactorEnabled() && !$recoveryCodes && !$reconfiguring) {
            return redirect()->route('portal.security');
        }

        $secret = null;
        $qrSvg = null;
        if (!$recoveryCodes) {
            $secret = $request->session()->get(self::PENDING_SECRET_KEY);
            if (!$secret) {
                $secret = $this->totp->generateSecret();
                $request->session()->put(self::PENDING_SECRET_KEY, $secret);
            }
            $qrSvg = $this->totp->qrCodeSvg($this->totp->provisioningUri($secret, $portalUser->email, self::ISSUER));
        }

        return view('portal.two-factor.setup', [
            'portalUser' => $portalUser,
            'secret' => $secret,
            'qrSvg' => $qrSvg,
            'recoveryCodes' => $recoveryCodes,
            'onboarding' => $request->boolean('onboarding') || $portalUser->needsTwoFactorPrompt(),
            'required' => $portalUser->twoFactorRequired(),
            'reconfiguring' => (bool) $reconfiguring,
            'enforcing' => (bool) $request->session()->get(self::ENFORCE_AFTER_SETUP_KEY),
        ]);
    }

    /** Move 2FA to a new phone: the current app keeps working until the new one is confirmed. */
    public function reconfigure(Request $request)
    {
        $portalUser = $this->portalUser();
        $this->confirmPassword($request, $portalUser);
        abort_unless($portalUser->hasTwoFactorEnabled(), 404);

        $request->session()->forget(self::PENDING_SECRET_KEY);
        $request->session()->put(self::RECONFIGURE_KEY, true);

        return redirect()->route('portal.two-factor.setup');
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);
        $portalUser = $this->portalUser();
        $secret = $request->session()->get(self::PENDING_SECRET_KEY);

        if ($portalUser->hasTwoFactorEnabled() && !$request->session()->get(self::RECONFIGURE_KEY)) {
            return redirect()->route('portal.security');
        }

        $step = $secret ? $this->totp->verify($secret, $request->input('code')) : null;
        if ($step === null) {
            return back()->withErrors(['code' => 'That code is not valid. Make sure you scanned the latest QR code and that your phone\'s time is correct.']);
        }

        $codes = $this->newRecoveryCodes();
        $portalUser->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'two_factor_prompt_pending' => false,
        ])->save();
        $wasReconfigure = (bool) $request->session()->pull(self::RECONFIGURE_KEY);
        $request->session()->forget(self::PENDING_SECRET_KEY);

        // A company that flipped "mandatory for my company" before having 2FA itself.
        $enforced = $request->session()->pull(self::ENFORCE_AFTER_SETUP_KEY) && $portalUser->isAgency();
        if ($enforced) {
            $portalUser->forceFill(['two_factor_enforced' => true])->save();
        }

        return redirect()->route('portal.two-factor.setup', ['onboarding' => $request->boolean('onboarding') ? 1 : null])
            ->with('two_factor_recovery_codes', $codes)
            ->with('success', match (true) {
                $wasReconfigure => 'Your new authenticator app is set up. The old one no longer works.',
                $enforced => 'Two-factor authentication is enabled, and is now mandatory for all agents in your company.',
                default => 'Two-factor authentication is now enabled.',
            });
    }

    /** "Skip for now" on the sign-up step — they can enable it later from Security. */
    public function skip()
    {
        $portalUser = $this->portalUser();
        if (!$portalUser->twoFactorRequired()) {
            $portalUser->forceFill(['two_factor_prompt_pending' => false])->save();
        }

        return redirect()->route('portal.dashboard');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $portalUser = $this->portalUser();
        $this->confirmPassword($request, $portalUser);
        abort_unless($portalUser->hasTwoFactorEnabled(), 404);

        $codes = $this->newRecoveryCodes();
        $portalUser->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return redirect()->route('portal.two-factor.setup')
            ->with('two_factor_recovery_codes', $codes)
            ->with('success', 'New recovery codes generated — the old ones no longer work.');
    }

    public function disable(Request $request)
    {
        $portalUser = $this->portalUser();
        $this->confirmPassword($request, $portalUser);

        if ($portalUser->twoFactorRequired()) {
            return redirect()->route('portal.security')
                ->with('error', $portalUser->isAgency()
                    ? 'Turn off "Mandatory 2FA for my company" first.'
                    : 'Your agency requires two-factor authentication, so it can\'t be turned off.');
        }

        $portalUser->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return redirect()->route('portal.security')
            ->with('success', 'Two-factor authentication has been turned off.');
    }

    /** Company only: make 2FA mandatory for the company account and every agent under it. */
    public function enforce(Request $request)
    {
        $portalUser = $this->portalUser();
        abort_unless($portalUser->isAgency(), 403);

        $enforce = $request->boolean('enforce');
        // Company admin must have 2FA too: set it up first, and enforcement switches on right after.
        if ($enforce && !$portalUser->hasTwoFactorEnabled()) {
            $request->session()->put(self::ENFORCE_AFTER_SETUP_KEY, true);
            return redirect()->route('portal.two-factor.setup');
        }
        $request->session()->forget(self::ENFORCE_AFTER_SETUP_KEY);

        $portalUser->forceFill(['two_factor_enforced' => $enforce])->save();

        return redirect()->route('portal.security')
            ->with('success', $enforce
                ? 'Two-factor authentication is now mandatory for all agents in your company. They\'ll be asked to set it up on their next visit.'
                : 'Two-factor authentication is no longer mandatory for your company.');
    }

    /** Signs every other browser out of "trusted": their next login needs the email code again. */
    public function forgetDevices(Request $request, PortalDeviceTrust $devices)
    {
        $portalUser = $this->portalUser();
        $devices->forgetAll($portalUser);
        $devices->trust($request, $portalUser);

        return redirect()->route('portal.security')
            ->with('success', 'All other devices will need email verification on their next sign-in.');
    }

    private function portalUser(): PortalUser
    {
        return Auth::guard('portal')->user();
    }

    private function confirmPassword(Request $request, PortalUser $portalUser): void
    {
        $request->validate(['password' => 'required|string']);

        if (!Hash::check($request->input('password'), $portalUser->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
    }

    /** @return list<string> e.g. "A1B2C-D3E4F" */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtoupper(Str::random(5) . '-' . Str::random(5)))
            ->all();
    }
}
