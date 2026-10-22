<?php

namespace App\Services\TwoFactor;

use App\Models\PortalUser;
use Illuminate\Support\Str;

/**
 * Turning an agent / company's authenticator-app 2FA on and off — the account changes behind the
 * CRM API (Crm\Account\TwoFactorController, used by the CRM web app's Security screens and the
 * mobile app). The not-yet-confirmed secret is kept in the cache between "show QR" and "confirm".
 */
class PortalTwoFactor
{
    public const ISSUER = 'MW Realty';

    public function __construct(private Totp $totp)
    {
    }

    public function newSecret(): string
    {
        return $this->totp->generateSecret();
    }

    public function provisioningUri(PortalUser $portalUser, string $secret): string
    {
        return $this->totp->provisioningUri($secret, $portalUser->email, self::ISSUER);
    }

    public function qrCodeSvg(PortalUser $portalUser, string $secret): string
    {
        return $this->totp->qrCodeSvg($this->provisioningUri($portalUser, $secret));
    }

    /**
     * Confirms the first code from the authenticator app and switches 2FA on with that secret.
     * Returns the new recovery codes, or null when the code is wrong.
     *
     * @return list<string>|null
     */
    public function enable(PortalUser $portalUser, string $secret, string $code): ?array
    {
        $step = $this->totp->verify($secret, $code);
        if ($step === null) {
            return null;
        }

        $codes = $this->newRecoveryCodes();
        $portalUser->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'two_factor_prompt_pending' => false,
        ])->save();

        return $codes;
    }

    public function disable(PortalUser $portalUser): void
    {
        $portalUser->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /** "Skip for now" after sign-up — only when nobody (their agency) requires it. */
    public function skip(PortalUser $portalUser): void
    {
        if (!$portalUser->twoFactorRequired()) {
            $portalUser->forceFill(['two_factor_prompt_pending' => false])->save();
        }
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(PortalUser $portalUser): array
    {
        $codes = $this->newRecoveryCodes();
        $portalUser->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    /** @return list<string> e.g. "A1B2C-D3E4F" */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtoupper(Str::random(5) . '-' . Str::random(5)))
            ->all();
    }
}
