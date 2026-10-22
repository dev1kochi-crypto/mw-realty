<?php

namespace App\Services\TwoFactor;

use App\Mail\NewDeviceLoginCodeMail;
use App\Models\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The agent/company sign-in checks after the password — the new-device emailed code and the
 * authenticator-app (or recovery) code. Shared by the website's session login
 * (Portal\PortalAuthController) and the CRM API's token login (Crm\Auth\AuthController), so
 * both apply exactly the same rules; only where the pending challenge is kept differs
 * (session vs cache).
 */
class PortalLoginVerifier
{
    public function __construct(private Totp $totp)
    {
    }

    /**
     * Emails a new-device code and returns what to keep in the pending challenge
     * (['hash' => …, 'expires_at' => …]), or null when the email could not be sent.
     */
    public function sendEmailCode(Request $request, PortalUser $portalUser): ?array
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
                return null;
            }
        }

        return [
            'hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];
    }

    /** Checks a code against what sendEmailCode() returned. */
    public function emailCodeMatches(mixed $sent, string $code): bool
    {
        return is_array($sent)
            && $sent['expires_at'] >= now()->timestamp
            && Hash::check(trim($code), $sent['hash']);
    }

    /** A 6-digit authenticator-app code, or one of the recovery codes (used up once accepted). */
    public function verifyTwoFactorCode(PortalUser $portalUser, string $code): bool
    {
        $code = trim($code);
        $step = $this->totp->verify((string) $portalUser->two_factor_secret, $code, $portalUser->two_factor_last_step);

        if ($step !== null) {
            $portalUser->forceFill(['two_factor_last_step' => $step])->save();
            return true;
        }

        return $this->useRecoveryCode($portalUser, $code);
    }

    public function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2) . str_repeat('•', max(1, mb_strlen($local) - 2)) . '@' . $domain;
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
}
