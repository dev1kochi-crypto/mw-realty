<?php

namespace App\Http\Controllers\Crm\Auth;

use App\Http\Resources\Crm\CrmUserResource;
use App\Models\PortalUser;
use App\Services\TwoFactor\PortalDeviceTrust;
use App\Services\TwoFactor\PortalLoginVerifier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @group CRM Auth
 *
 * Token (Bearer) sign-in for the CRM mobile app — **agents and agencies** only.
 *
 * Same accounts and the same checks as the website's portal login (Portal\PortalAuthController):
 * password, then — when the account has two-factor on — an emailed code for a device it hasn't
 * signed in from before, then the authenticator-app code. The steps are linked by the
 * `challenge_token` returned by each step instead of a session.
 *
 * Keep the `device_token` returned on success and send it with the next login, so a known
 * device skips the emailed code. Send the `token` on every other request:
 * `Authorization: Bearer {token}`.
 */
class AuthController extends Controller
{
    private const CHALLENGE_TTL_MINUTES = 15;
    private const MAX_CHALLENGE_ATTEMPTS = 5;

    public function __construct(private PortalDeviceTrust $devices, private PortalLoginVerifier $verifier)
    {
    }

    /**
     * Sign in
     *
     * Either signs in straight away (`token` in the response), or asks for the next step:
     * `challenge: "email"` → `POST /api/crm/auth/login/verify-email`,
     * `challenge: "totp"` → `POST /api/crm/auth/login/two-factor`, passing the `challenge_token`.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: agent@example.com
     * @bodyParam password string required Example: secret123
     * @bodyParam device_name string Shown in the account's signed-in devices. Example: Sara's iPhone
     * @bodyParam device_token string The `device_token` from this device's last sign-in. Example: 9fK2…
     *
     * @response 200 scenario="Signed in" {"token": "1|abc…", "token_type": "Bearer", "device_token": "9fK2…", "user": {"id": 12, "type": "agent", "name": "Sara Ahmed"}}
     * @response 200 scenario="New device" {"challenge": "email", "email": "sa••••@example.com", "challenge_token": "Xc3…"}
     * @response 200 scenario="Two-factor on" {"challenge": "totp", "challenge_token": "Xc3…"}
     * @response 422 {"message": "Invalid credentials."}
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
            'device_token' => 'nullable|string|max:100',
        ]);

        $portalUser = PortalUser::where('email', $request->input('email'))->first();

        if (!$portalUser || !Hash::check($request->input('password'), $portalUser->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        // Pending / rejected accounts can sign in (profile + KYC only, see portal.approved); disabled can't.
        if (!$portalUser->is_active) {
            return response()->json(['message' => 'Your account has been disabled. Contact the site administrator.'], 422);
        }

        $needsTotp = $portalUser->hasTwoFactorEnabled();
        $needsEmail = $needsTotp && !$this->devices->isTrustedToken($portalUser, $request->input('device_token'));

        $challenge = [
            'user_id' => $portalUser->id,
            'device_name' => $request->input('device_name'),
            'device_token' => $request->input('device_token'),
            'email_verified' => !$needsEmail,
            'attempts' => 0,
        ];

        if (!$needsEmail && !$needsTotp) {
            return $this->completeLogin($request, $portalUser, $challenge);
        }

        $challengeToken = Str::random(48);

        if ($needsEmail) {
            if (!$challenge['email_code'] = $this->verifier->sendEmailCode($request, $portalUser)) {
                return response()->json(['message' => 'Could not send the verification email right now — please try again in a moment.'], 503);
            }
            $this->putChallenge($challengeToken, $challenge);

            return response()->json([
                'challenge' => 'email',
                'email' => $this->verifier->maskEmail($portalUser->email),
                'challenge_token' => $challengeToken,
            ]);
        }

        $this->putChallenge($challengeToken, $challenge);

        return response()->json(['challenge' => 'totp', 'challenge_token' => $challengeToken]);
    }

    /**
     * Verify new-device code
     *
     * The 6-digit code emailed by the sign-in step. Answers like Sign in: a `token`, or
     * `challenge: "totp"` for the authenticator-app step. `restart: true` = start over with the password.
     *
     * @unauthenticated
     *
     * @bodyParam challenge_token string required Example: Xc3…
     * @bodyParam code string required Example: 482913
     *
     * @response 200 {"challenge": "totp", "challenge_token": "Xc3…"}
     * @response 422 {"message": "Invalid or expired code."}
     */
    public function verifyEmail(Request $request)
    {
        $request->validate(['challenge_token' => 'required|string', 'code' => 'required|string|max:20']);
        $challengeToken = $request->input('challenge_token');
        [$challenge, $portalUser] = $this->pendingChallenge($challengeToken);
        if (!$portalUser) {
            return $this->challengeExpired();
        }

        if (!$this->verifier->emailCodeMatches($challenge['email_code'] ?? null, $request->input('code'))) {
            return $this->failedAttempt($challengeToken, $challenge, 'Invalid or expired code.');
        }

        $challenge['email_verified'] = true;
        unset($challenge['email_code']);

        if ($portalUser->hasTwoFactorEnabled()) {
            $this->putChallenge($challengeToken, $challenge);

            return response()->json(['challenge' => 'totp', 'challenge_token' => $challengeToken]);
        }

        Cache::forget($this->challengeKey($challengeToken));

        return $this->completeLogin($request, $portalUser, $challenge);
    }

    /**
     * Resend new-device code
     *
     * @unauthenticated
     *
     * @bodyParam challenge_token string required Example: Xc3…
     *
     * @response 200 {"message": "A new code has been sent."}
     */
    public function resendEmail(Request $request)
    {
        $request->validate(['challenge_token' => 'required|string']);
        $challengeToken = $request->input('challenge_token');
        [$challenge, $portalUser] = $this->pendingChallenge($challengeToken);
        if (!$portalUser || $challenge['email_verified']) {
            return $this->challengeExpired();
        }

        if (!$challenge['email_code'] = $this->verifier->sendEmailCode($request, $portalUser)) {
            return response()->json(['message' => 'Could not send the code right now — please try again in a moment.'], 503);
        }
        $this->putChallenge($challengeToken, $challenge);

        return response()->json(['message' => 'A new code has been sent.']);
    }

    /**
     * Verify two-factor code
     *
     * The authenticator-app code, or one of the account's recovery codes. Returns the `token`.
     *
     * @unauthenticated
     *
     * @bodyParam challenge_token string required Example: Xc3…
     * @bodyParam code string required Example: 123456
     *
     * @response 200 {"token": "1|abc…", "token_type": "Bearer", "device_token": "9fK2…", "user": {"id": 12, "type": "agent", "name": "Sara Ahmed"}}
     * @response 422 {"message": "That code is not valid. Check your authenticator app and try again."}
     */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['challenge_token' => 'required|string', 'code' => 'required|string|max:30']);
        $challengeToken = $request->input('challenge_token');
        [$challenge, $portalUser] = $this->pendingChallenge($challengeToken);
        if (!$portalUser || !$challenge['email_verified']) {
            return $this->challengeExpired();
        }

        if (!$this->verifier->verifyTwoFactorCode($portalUser, $request->input('code'))) {
            return $this->failedAttempt($challengeToken, $challenge, 'That code is not valid. Check your authenticator app and try again.');
        }

        Cache::forget($this->challengeKey($challengeToken));

        return $this->completeLogin($request, $portalUser, $challenge);
    }

    /**
     * Current account
     *
     * Who is signed in and what they can open — the app's start-up call. `approved: false`
     * means KYC is still pending: only profile screens work until Super Admin approves.
     *
     * @response 200 {"data": {"id": 12, "type": "agent", "name": "Sara Ahmed", "status": "approved", "approved": true, "is_super_admin": false}}
     */
    public function me(Request $request)
    {
        return new CrmUserResource($request->user());
    }

    /**
     * Sign out
     *
     * Revokes the token used for this request (other devices stay signed in).
     *
     * @response 200 {"message": "Signed out."}
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user instanceof PortalUser && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        } else {
            // The /crm web app's session — same as the portal's own sign-out.
            Auth::guard('portal')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->json(['message' => 'Signed out.']);
    }

    private function completeLogin(Request $request, PortalUser $portalUser, array $challenge)
    {
        $deviceToken = $this->devices->remember($request, $portalUser, $challenge['device_token'] ?? null);
        $token = $portalUser->createToken($challenge['device_name'] ?: Str::limit((string) $request->userAgent(), 100, '') ?: 'CRM app');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'device_token' => $deviceToken,
            'user' => new CrmUserResource($portalUser),
        ]);
    }

    private function challengeKey(string $challengeToken): string
    {
        return 'crm-login-challenge:' . hash('sha256', $challengeToken);
    }

    private function putChallenge(string $challengeToken, array $challenge): void
    {
        $challenge['expires_at'] ??= now()->addMinutes(self::CHALLENGE_TTL_MINUTES)->timestamp;
        Cache::put($this->challengeKey($challengeToken), $challenge, now()->setTimestamp($challenge['expires_at']));
    }

    /** @return array{0: ?array, 1: ?PortalUser} */
    private function pendingChallenge(string $challengeToken): array
    {
        $challenge = Cache::get($this->challengeKey($challengeToken));
        $portalUser = is_array($challenge) ? PortalUser::find($challenge['user_id']) : null;

        if (!$portalUser || !$portalUser->is_active) {
            Cache::forget($this->challengeKey($challengeToken));
            return [null, null];
        }

        return [$challenge, $portalUser];
    }

    private function challengeExpired()
    {
        return response()->json(['message' => 'Your sign-in session has expired. Please enter your email and password again.', 'restart' => true], 422);
    }

    /** Too many wrong codes ends the attempt — the user has to start over with their password. */
    private function failedAttempt(string $challengeToken, array $challenge, string $message)
    {
        if (++$challenge['attempts'] >= self::MAX_CHALLENGE_ATTEMPTS) {
            Cache::forget($this->challengeKey($challengeToken));
            return response()->json(['message' => 'Too many incorrect codes. Please sign in again.', 'restart' => true], 422);
        }

        $this->putChallenge($challengeToken, $challenge);

        return response()->json(['message' => $message], 422);
    }
}
