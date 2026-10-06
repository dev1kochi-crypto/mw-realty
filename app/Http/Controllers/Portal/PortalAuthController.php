<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use App\Models\Plan;
use App\Mail\NewDeviceLoginCodeMail;
use App\Mail\OtpCodeMail;
use App\Services\TwoFactor\PortalDeviceTrust;
use App\Services\TwoFactor\Totp;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PortalAuthController extends Controller
{
    private const CHALLENGE_KEY = 'portal_login_challenge';
    private const MAX_CHALLENGE_ATTEMPTS = 5;

    public function __construct(private PortalDeviceTrust $devices, private Totp $totp)
    {
    }

    public function showRegister()
    {
        $companies = PortalUser::companies()->approved()->orderBy('company_name')->get(['id', 'company_name', 'name']);
        return view('portal.auth.register', compact('companies'));
    }

    public function register(Request $request)
    {
        $request->validate([
            'type' => ['required', Rule::in(['company', 'agent'])],
            'name' => 'required|string|max:255',
            'company_name' => 'required_if:type,company|nullable|string|max:255',
            'email' => 'required|email|unique:portal_users,email',
            'phone' => \App\Rules\PhoneNumber::rules(),
            'phone_country_code' => \App\Rules\PhoneNumber::countryCodeRules(),
            'password' => ['required', 'confirmed', Password::min(8)],

            'nationality' => 'nullable|string|max:100',
            'emirates_id_no' => 'nullable|string|max:50',
            'emirates_id_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'passport_no' => 'nullable|string|max:50',
            'passport_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',

            'brn_number' => 'nullable|string|max:50',
            'rera_card_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'company_id' => ['nullable', Rule::exists('portal_users', 'id')->where('type', 'company')],

            'trade_license_no' => 'nullable|string|max:50',
            'trade_license_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'trade_license_expiry' => 'nullable|date',
            'orn_number' => 'nullable|string|max:50',
            'rera_certificate_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'office_address' => 'nullable|string|max:255',
            'trn_number' => 'nullable|string|max:50',
            'authorized_signatory_name' => 'nullable|string|max:255',
            'landline' => 'nullable|string|max:50',
        ]);

        $portalUser = PortalUser::create([
            'type' => $request->input('type'),
            'name' => $request->input('name'),
            'company_name' => $request->input('type') === 'company' ? $request->input('company_name') : null,
            'email' => $request->input('email'),
            'phone' => \App\Rules\PhoneNumber::combine($request->input('phone_country_code'), $request->input('phone')),
            'password' => Hash::make($request->input('password')),
            'status' => 'pending',
            'kyc_review_status' => 'draft',
            'status_changed_at' => now(),
            'plan_id' => Plan::defaultFree()?->id,

            'nationality' => $request->input('nationality'),
            'emirates_id_no' => $request->input('emirates_id_no'),
            'passport_no' => $request->input('passport_no'),

            'brn_number' => $request->input('type') === 'agent' ? $request->input('brn_number') : null,

            'trade_license_no' => $request->input('type') === 'company' ? $request->input('trade_license_no') : null,
            'trade_license_expiry' => $request->input('type') === 'company' ? $request->input('trade_license_expiry') : null,
            'orn_number' => $request->input('type') === 'company' ? $request->input('orn_number') : null,
            'office_address' => $request->input('type') === 'company' ? $request->input('office_address') : null,
            'trn_number' => $request->input('type') === 'company' ? $request->input('trn_number') : null,
            'authorized_signatory_name' => $request->input('type') === 'company' ? $request->input('authorized_signatory_name') : null,
            'landline' => $request->input('type') === 'company' ? $request->input('landline') : null,
        ]);

        $this->storeKycDocuments($request, $portalUser);

        // Picking an agency at sign-up is a join request (agency accepts, admin approves) — the
        // agent starts out independent either way, on the same single account.
        if ($portalUser->isAgent() && $request->filled('company_id')) {
            try {
                app(\App\Services\Agency\AgencyMembershipService::class)
                    ->requestToJoin($portalUser, PortalUser::findOrFail($request->input('company_id')));
            } catch (\Illuminate\Validation\ValidationException) {
                // Agency not currently accepting agents — the agent can request again from My Agency.
            }
        }

        // The account remains a KYC draft until the user completes email verification, their
        // profile and documents, then explicitly submits it for admin review.
        $sent = $this->issueOtp($portalUser);

        return response()->json([
            'otp_required' => true,
            'user_id' => $portalUser->id,
            'email_sent' => $sent,
        ]);
    }

    /**
     * Shared by register() and resendOtp() — same shape as
     * CustomerAuthController::issueOtp(), sent synchronously so a code the user is waiting on
     * right now can't sit in the jobs table until a queue worker runs.
     */
    private function issueOtp(PortalUser $portalUser): bool
    {
        // OTP_ENABLED=false (testing): skip the email and accept the static OTP_STATIC_CODE.
        if (!config('auth.otp.enabled')) {
            $code = (string) config('auth.otp.static_code');
        } else {
            $code = (string) random_int(1000, 9999);

            try {
                Mail::to($portalUser->email)->send(new OtpCodeMail($portalUser->displayName(), $code));
            } catch (\Throwable $e) {
                Log::error('Failed to send portal OTP code email: ' . $e->getMessage());
                return false;
            }
        }

        $portalUser->forceFill([
            'otp_code' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        return true;
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:portal_users,id',
            'code' => 'required|string',
        ]);

        $portalUser = PortalUser::findOrFail($request->input('user_id'));

        if (
            !$portalUser->otp_code
            || !$portalUser->otp_expires_at
            || $portalUser->otp_expires_at->isPast()
            || !Hash::check($request->input('code'), $portalUser->otp_code)
        ) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $portalUser->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
            // Next sign-up step: set up 2FA or explicitly skip it (EnsurePortalTwoFactor holds them there).
            'two_factor_prompt_pending' => !$portalUser->hasTwoFactorEnabled(),
        ])->save();

        Auth::guard('portal')->login($portalUser);
        $request->session()->regenerate();
        $request->session()->put('password_hash_portal', $portalUser->getAuthPassword());
        // The email was just verified from this browser, so later logins here skip the new-device code.
        $this->devices->trust($request, $portalUser);

        // Next step of sign-up: offer authenticator-app 2FA (skippable unless an agency enforces it).
        return response()->json(['redirect' => route('portal.two-factor.setup', ['onboarding' => 1])]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:portal_users,id',
        ]);

        $portalUser = PortalUser::findOrFail($request->input('user_id'));

        if (!$portalUser->otp_code) {
            return response()->json(['message' => 'This account is already verified.'], 422);
        }

        if (!$this->issueOtp($portalUser)) {
            return response()->json(['message' => 'Could not send the code right now — please try again in a moment.'], 503);
        }

        return response()->json(['message' => 'A new code has been sent.']);
    }

    private function storeKycDocuments(Request $request, PortalUser $portalUser): void
    {
        $documentFields = [
            'emirates_id_document', 'passport_document', 'rera_card_document',
            'trade_license_document', 'rera_certificate_document',
        ];

        $updates = [];
        foreach ($documentFields as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $updates[$field] = app(\App\Services\ManagedFiles::class)->store($file, 'portal-kyc', 'kyc');
            }
        }

        if ($updates) {
            $portalUser->update($updates);
        }
    }

    public function showLogin()
    {
        // The Vue frontend's /login page is now the only login UI (it posts
        // straight to /portal/login below) — this GET route just exists for
        // the other flows still named 'portal.login' (logout, the
        // redirectGuestsTo default in bootstrap/app.php, post-registration)
        // to redirect through.
        return redirect('/login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $portalUser = PortalUser::where('email', $request->input('email'))->first();

        if (!$portalUser || !Hash::check($request->input('password'), $portalUser->password)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid credentials.'], 422);
            }
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email', 'login_type');
        }

        // Pending and rejected accounts CAN log in — restricted to viewing/completing
        // their profile (and, once rejected, resubmitting) until Super Admin approves
        // them; only property creation is gated on approval. is_active is a hard stop.
        if (!$portalUser->is_active) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Your account has been disabled. Contact the site administrator.'], 422);
            }
            return back()->withErrors(['email' => 'Your account has been disabled. Contact the site administrator.'])->onlyInput('email', 'login_type');
        }

        // Password is right — now the extra checks: an emailed code when this browser hasn't been
        // verified for this account before, then the authenticator-app code if 2FA is on.
        // Both only apply once the account has 2FA on — without it, the password alone signs in.
        $needsTotp = $portalUser->hasTwoFactorEnabled();
        $needsEmail = $needsTotp && !$this->devices->isTrusted($request, $portalUser);

        if (!$needsEmail && !$needsTotp) {
            return $this->completeLogin($request, $portalUser, $request->boolean('remember'));
        }

        $request->session()->put(self::CHALLENGE_KEY, [
            'user_id' => $portalUser->id,
            'remember' => $request->boolean('remember'),
            'email_verified' => !$needsEmail,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        if ($needsEmail) {
            if (!$this->sendLoginEmailCode($request, $portalUser)) {
                $request->session()->forget(self::CHALLENGE_KEY);
                return response()->json(['message' => 'Could not send the verification email right now — please try again in a moment.'], 503);
            }

            return response()->json(['challenge' => 'email', 'email' => $this->maskEmail($portalUser->email)]);
        }

        return response()->json(['challenge' => 'totp']);
    }

    /** Step 2 for a new device: the code emailed by login(). */
    public function verifyLoginEmail(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);
        [$challenge, $portalUser] = $this->pendingChallenge($request);
        if (!$portalUser) {
            return $this->challengeExpired();
        }

        $sent = $request->session()->get(self::CHALLENGE_KEY . '.email_code');
        $valid = is_array($sent)
            && $sent['expires_at'] >= now()->timestamp
            && Hash::check(trim($request->input('code')), $sent['hash']);

        if (!$valid) {
            return $this->failedAttempt($request, 'Invalid or expired code.');
        }

        $challenge['email_verified'] = true;
        unset($challenge['email_code']);
        $request->session()->put(self::CHALLENGE_KEY, $challenge);

        if ($portalUser->hasTwoFactorEnabled()) {
            return response()->json(['challenge' => 'totp']);
        }

        return $this->completeLogin($request, $portalUser, $challenge['remember']);
    }

    public function resendLoginEmail(Request $request)
    {
        [, $portalUser] = $this->pendingChallenge($request);
        if (!$portalUser) {
            return $this->challengeExpired();
        }

        if (!$this->sendLoginEmailCode($request, $portalUser)) {
            return response()->json(['message' => 'Could not send the code right now — please try again in a moment.'], 503);
        }

        return response()->json(['message' => 'A new code has been sent.']);
    }

    /** Final step when 2FA is on: a 6-digit authenticator-app code, or one of the recovery codes. */
    public function verifyLoginTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string|max:30']);
        [$challenge, $portalUser] = $this->pendingChallenge($request);
        if (!$portalUser || !$challenge['email_verified']) {
            return $this->challengeExpired();
        }

        $code = trim($request->input('code'));
        $step = $this->totp->verify((string) $portalUser->two_factor_secret, $code, $portalUser->two_factor_last_step);

        if ($step !== null) {
            $portalUser->forceFill(['two_factor_last_step' => $step])->save();
        } elseif (!$this->useRecoveryCode($portalUser, $code)) {
            return $this->failedAttempt($request, 'That code is not valid. Check your authenticator app and try again.');
        }

        return $this->completeLogin($request, $portalUser, $challenge['remember']);
    }

    private function completeLogin(Request $request, PortalUser $portalUser, bool $remember)
    {
        $request->session()->forget(self::CHALLENGE_KEY);
        Auth::guard('portal')->login($portalUser, $remember);
        $request->session()->regenerate();
        $request->session()->put('password_hash_portal', $portalUser->getAuthPassword());
        $this->devices->trust($request, $portalUser);

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('portal.dashboard')]);
        }

        return redirect()->route('portal.dashboard');
    }

    /** @return array{0: ?array, 1: ?PortalUser} */
    private function pendingChallenge(Request $request): array
    {
        $challenge = $request->session()->get(self::CHALLENGE_KEY);
        if (!is_array($challenge) || $challenge['expires_at'] < now()->timestamp) {
            $request->session()->forget(self::CHALLENGE_KEY);
            return [null, null];
        }

        $portalUser = PortalUser::find($challenge['user_id']);
        if (!$portalUser || !$portalUser->is_active) {
            $request->session()->forget(self::CHALLENGE_KEY);
            return [null, null];
        }

        return [$challenge, $portalUser];
    }

    private function challengeExpired()
    {
        return response()->json(['message' => 'Your sign-in session has expired. Please enter your email and password again.', 'restart' => true], 422);
    }

    /** Too many wrong codes ends the attempt — the user has to start over with their password. */
    private function failedAttempt(Request $request, string $message)
    {
        $attempts = $request->session()->get(self::CHALLENGE_KEY . '.attempts', 0) + 1;
        if ($attempts >= self::MAX_CHALLENGE_ATTEMPTS) {
            $request->session()->forget(self::CHALLENGE_KEY);
            return response()->json(['message' => 'Too many incorrect codes. Please sign in again.', 'restart' => true], 422);
        }

        $request->session()->put(self::CHALLENGE_KEY . '.attempts', $attempts);

        return response()->json(['message' => $message], 422);
    }

    private function sendLoginEmailCode(Request $request, PortalUser $portalUser): bool
    {
        // OTP_ENABLED=false (testing): skip the email and accept the static OTP_STATIC_CODE.
        if (!config('auth.otp.enabled')) {
            $code = (string) config('auth.otp.static_code');
        } else {
            $code = (string) random_int(100000, 999999);

            try {
                Mail::to($portalUser->email)->send(new NewDeviceLoginCodeMail(
                    $portalUser->displayName(),
                    $code,
                    $this->describeDevice((string) $request->userAgent()),
                    $request->ip(),
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to send portal new-device login code: ' . $e->getMessage());
                return false;
            }
        }

        $request->session()->put(self::CHALLENGE_KEY . '.email_code', [
            'hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return true;
    }

    private function useRecoveryCode(PortalUser $portalUser, string $code): bool
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', $code));
        $codes = $portalUser->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $stored) {
            if (hash_equals(str_replace('-', '', $stored), $normalized)) {
                unset($codes[$i]);
                $portalUser->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                return true;
            }
        }

        return false;
    }

    private function describeDevice(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'unknown OS',
        };

        return "{$browser} on {$os}";
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2) . str_repeat('•', max(1, mb_strlen($local) - 2)) . '@' . $domain;
    }

    public function logout(Request $request)
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
