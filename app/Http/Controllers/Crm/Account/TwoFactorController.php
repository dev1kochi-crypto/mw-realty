<?php

namespace App\Http\Controllers\Crm\Account;

use App\Models\PortalUser;
use App\Services\TwoFactor\PortalDeviceTrust;
use App\Services\TwoFactor\PortalTwoFactor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group CRM Account
 *
 * Authenticator-app two-factor authentication (Google Authenticator, Authy…) for the signed-in
 * agent / company — the app's version of the portal's Security screen. Reachable before 2FA is
 * set up, so an account that `/auth/me` reports as `two_factor.setup_required` can finish it here.
 *
 * Set-up: `POST /setup` (shows the secret / QR) → scan it → `POST /confirm` with the first code.
 */
class TwoFactorController extends Controller
{
    private const PENDING_SECRET_MINUTES = 30;

    public function __construct(private PortalTwoFactor $twoFactor)
    {
    }

    /**
     * Two-factor status
     *
     * @response 200 {"enabled": false, "required": false, "setup_required": true, "can_skip": true, "company_enforced": false, "recovery_codes_left": 0, "trusted_devices": []}
     */
    public function show()
    {
        $portalUser = $this->portalUser();
        $enabled = $portalUser->hasTwoFactorEnabled();

        return response()->json([
            'email' => $portalUser->email,
            'is_agency' => $portalUser->isAgency(),
            'enabled' => $enabled,
            'required' => $portalUser->twoFactorRequired(),
            'setup_required' => !$enabled && ($portalUser->twoFactorRequired() || $portalUser->needsTwoFactorPrompt()),
            'can_skip' => !$enabled && !$portalUser->twoFactorRequired() && $portalUser->needsTwoFactorPrompt(),
            'company_enforced' => $portalUser->isAgency() ? (bool) $portalUser->two_factor_enforced : null,
            'recovery_codes_left' => $enabled ? count($portalUser->two_factor_recovery_codes ?? []) : 0,
            'trusted_devices' => DB::table('portal_trusted_devices')->where('portal_user_id', $portalUser->id)
                ->orderByDesc('last_used_at')->get(['user_agent', 'ip_address', 'last_used_at']),
        ]);
    }

    /**
     * Start set-up
     *
     * A new secret to add to the authenticator app — as a QR code (`qr_svg`), an `otpauth://`
     * link (`uri`, opens the app directly on the same phone) or typed in (`secret`). Moving 2FA
     * that is already on to a new phone needs the account `password`; the old app keeps working
     * until the new one is confirmed.
     *
     * @bodyParam password string Only when 2FA is already on. Example: secret123
     *
     * @response 200 {"secret": "JBSWY3DPEHPK3PXP", "uri": "otpauth://totp/MW%20Realty:agent@example.com?secret=…", "qr_svg": "<svg…>"}
     */
    public function setup(Request $request)
    {
        $portalUser = $this->portalUser();
        if ($portalUser->hasTwoFactorEnabled()) {
            $this->confirmPassword($request, $portalUser);
        }

        $secret = $this->twoFactor->newSecret();
        Cache::put($this->pendingKey($portalUser), $secret, now()->addMinutes(self::PENDING_SECRET_MINUTES));

        return response()->json([
            'secret' => $secret,
            'uri' => $this->twoFactor->provisioningUri($portalUser, $secret),
            'qr_svg' => $this->twoFactor->qrCodeSvg($portalUser, $secret),
        ]);
    }

    /**
     * Confirm set-up
     *
     * The first code the authenticator app shows. Switches 2FA on and returns the recovery
     * codes — show them once and ask the user to keep them safe. A company can pass
     * `enforce: true` to make 2FA mandatory for every agent right away.
     *
     * @bodyParam code string required Example: 123456
     * @bodyParam enforce boolean Company only. Example: false
     *
     * @response 200 {"success": true, "message": "Two-factor authentication is now enabled.", "recovery_codes": ["A1B2C-D3E4F"]}
     * @response 422 {"message": "That code is not valid. Make sure you scanned the latest QR code and that your phone's time is correct."}
     */
    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20', 'enforce' => 'nullable|boolean']);
        $portalUser = $this->portalUser();
        $secret = Cache::get($this->pendingKey($portalUser));

        if (!$secret) {
            throw ValidationException::withMessages(['code' => 'Your set-up expired — start again to get a new QR code.']);
        }

        $codes = $this->twoFactor->enable($portalUser, $secret, $request->input('code'));
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Make sure you scanned the latest QR code and that your phone\'s time is correct.']);
        }
        Cache::forget($this->pendingKey($portalUser));

        $enforced = $request->boolean('enforce') && $portalUser->isAgency();
        if ($enforced) {
            $portalUser->forceFill(['two_factor_enforced' => true])->save();
        }

        return response()->json([
            'success' => true,
            'message' => $enforced
                ? 'Two-factor authentication is enabled, and is now mandatory for all agents in your company.'
                : 'Two-factor authentication is now enabled.',
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * Skip for now
     *
     * After sign-up, when 2FA isn't required by the agency — it can be turned on later.
     *
     * @response 200 {"success": true}
     */
    public function skip()
    {
        $portalUser = $this->portalUser();
        if ($portalUser->twoFactorRequired()) {
            return response()->json(['message' => 'Your agency requires two-factor authentication.'], 422);
        }
        $this->twoFactor->skip($portalUser);

        return response()->json(['success' => true]);
    }

    /**
     * New recovery codes
     *
     * The old ones stop working.
     *
     * @bodyParam password string required Example: secret123
     *
     * @response 200 {"success": true, "recovery_codes": ["A1B2C-D3E4F"]}
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $portalUser = $this->portalUser();
        $this->confirmPassword($request, $portalUser);
        abort_unless($portalUser->hasTwoFactorEnabled(), 404);

        return response()->json(['success' => true, 'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($portalUser)]);
    }

    /**
     * Turn off
     *
     * Not possible while the agency (or, for a company, its own "mandatory" switch) requires it.
     *
     * @bodyParam password string required Example: secret123
     *
     * @response 200 {"success": true, "message": "Two-factor authentication has been turned off."}
     */
    public function disable(Request $request)
    {
        $portalUser = $this->portalUser();
        $this->confirmPassword($request, $portalUser);

        if ($portalUser->twoFactorRequired()) {
            return response()->json(['message' => $portalUser->isAgency()
                ? 'Turn off "Mandatory 2FA for my company" first.'
                : 'Your agency requires two-factor authentication, so it can\'t be turned off.'], 422);
        }

        $this->twoFactor->disable($portalUser);

        return response()->json(['success' => true, 'message' => 'Two-factor authentication has been turned off.']);
    }

    /**
     * Mandatory for my company
     *
     * Company only. The company account needs 2FA itself first — set it up with `enforce: true`
     * on Confirm set-up instead.
     *
     * @bodyParam enforce boolean required Example: true
     *
     * @response 200 {"success": true, "message": "Two-factor authentication is now mandatory for all agents in your company."}
     */
    public function enforce(Request $request)
    {
        $request->validate(['enforce' => 'required|boolean']);
        $portalUser = $this->portalUser();
        abort_unless($portalUser->isAgency(), 403);

        $enforce = $request->boolean('enforce');
        if ($enforce && !$portalUser->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Set up two-factor authentication for your own account first.', 'setup_first' => true], 422);
        }

        $portalUser->forceFill(['two_factor_enforced' => $enforce])->save();

        return response()->json(['success' => true, 'message' => $enforce
            ? 'Two-factor authentication is now mandatory for all agents in your company. They\'ll be asked to set it up on their next visit.'
            : 'Two-factor authentication is no longer mandatory for your company.']);
    }

    /**
     * Forget other devices
     *
     * Every other device needs the emailed code again on its next sign-in. From the web app this
     * browser stays recognised; from the mobile app every device (this one included) is forgotten.
     *
     * @response 200 {"success": true}
     */
    public function forgetDevices(Request $request, PortalDeviceTrust $devices)
    {
        $portalUser = $this->portalUser();
        $devices->forgetAll($portalUser);

        if ($request->hasSession()) {
            $devices->trust($request, $portalUser);

            return response()->json(['success' => true, 'message' => 'All other devices will need email verification on their next sign-in.']);
        }

        return response()->json(['success' => true, 'message' => 'All devices will need email verification on their next sign-in.']);
    }

    /** Agent / company only — a Super Admin's 2FA is managed in the CMS. */
    private function portalUser(): PortalUser
    {
        $portalUser = Auth::guard('portal')->user();
        abort_unless($portalUser, 403, 'Two-factor settings belong to agent and company accounts.');

        return $portalUser;
    }

    private function pendingKey(PortalUser $portalUser): string
    {
        return 'crm-2fa-pending:' . $portalUser->id;
    }

    private function confirmPassword(Request $request, PortalUser $portalUser): void
    {
        $request->validate(['password' => 'required|string']);

        if (!Hash::check($request->input('password'), $portalUser->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
    }
}
