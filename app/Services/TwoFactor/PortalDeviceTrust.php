<?php

namespace App\Services\TwoFactor;

use App\Models\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Is this a browser this portal user has signed in from before?" — a random token in a
 * long-lived (encrypted) cookie, stored hashed in portal_trusted_devices. A login from a browser
 * without one has to pass an emailed code first (see PortalAuthController::login()).
 */
class PortalDeviceTrust
{
    public const DAYS = 180;

    public function isTrusted(Request $request, PortalUser $user): bool
    {
        return $this->isTrustedToken($user, $request->cookie($this->cookieName($user)));
    }

    /**
     * Same check for a client without cookies (the mobile app): it sends back the
     * `device_token` it was given by remember() on its last successful sign-in.
     */
    public function isTrustedToken(PortalUser $user, mixed $token): bool
    {
        return is_string($token) && $token !== '' && DB::table('portal_trusted_devices')
            ->where('portal_user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('last_used_at', '>=', now()->subDays(self::DAYS))
            ->exists();
    }

    /** Remembers (or refreshes) the current browser for this user and queues its cookie. */
    public function trust(Request $request, PortalUser $user): void
    {
        $token = $this->remember($request, $user, $request->cookie($this->cookieName($user)));

        Cookie::queue(cookie($this->cookieName($user), $token, self::DAYS * 24 * 60, null, null, null, true, false, 'lax'));
    }

    /**
     * Refreshes the device the given token belongs to, or registers a new one — returns the
     * token to keep (unchanged when it was already known). trust() keeps it in a cookie; the
     * CRM API hands it to the app as `device_token`.
     */
    public function remember(Request $request, PortalUser $user, mixed $token): string
    {
        $row = is_string($token) && $token !== ''
            ? DB::table('portal_trusted_devices')->where('portal_user_id', $user->id)->where('token_hash', hash('sha256', $token))->first()
            : null;

        if ($row) {
            DB::table('portal_trusted_devices')->where('id', $row->id)->update([
                'ip_address' => $request->ip(),
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);

            return $token;
        }

        $token = Str::random(64);
        DB::table('portal_trusted_devices')->insert([
            'portal_user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    /** Signs every browser out of "trusted" — the next login anywhere needs the email code again. */
    public function forgetAll(PortalUser $user): void
    {
        DB::table('portal_trusted_devices')->where('portal_user_id', $user->id)->delete();
    }

    /** Per-user cookie, so two accounts sharing one browser don't evict each other's trust. */
    private function cookieName(PortalUser $user): string
    {
        return 'mw_portal_device_' . $user->id;
    }
}
