<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Customer\CustomerAuthController;
use App\Models\User;
use App\Services\Visitors\VisitorTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * @group User Auth
 *
 * Token (Bearer) auth for the mobile app's **User** account (buyers/visitors). Agents and
 * agencies don't sign in through these endpoints.
 *
 * Same accounts, OTP and Google rules as the website's session login
 * (Customer\CustomerAuthController) — the only difference is that a successful sign-in returns
 * a Sanctum token instead of a cookie.
 *
 * Send the token on every authenticated request: `Authorization: Bearer {token}`.
 */
class CustomerTokenAuthController extends CustomerAuthController
{
    /**
     * Sign up
     *
     * Step 1 of sign-up. Creates the account and emails a 4-digit code — no token yet; call
     * `POST /api/auth/verify-otp` with the returned `user_id` and the code.
     * `email_sent: false` means the email failed — offer "Resend code".
     *
     * @unauthenticated
     *
     * @bodyParam name string required Example: Sara Ahmed
     * @bodyParam email string required Must not already be registered. Example: buyer@example.com
     * @bodyParam phone string Local number without the dial code. Example: 501234567
     * @bodyParam phone_country_code string Dial code, required with phone. Example: +971
     * @bodyParam password string required At least 8 characters. Example: secret123
     * @bodyParam password_confirmation string required Must match password. Example: secret123
     *
     * @response 200 {"otp_required": true, "user_id": 7, "email_sent": true}
     * @response 422 scenario="Validation error" {"message": "The email has already been taken.", "errors": {"email": ["The email has already been taken."]}}
     */
    public function register(Request $request)
    {
        return parent::register($request);
    }

    /**
     * Resend sign-up code
     *
     * @unauthenticated
     *
     * @bodyParam user_id integer required Example: 7
     *
     * @response 200 {"message": "A new code has been sent."}
     * @response 422 {"message": "This account is already verified."}
     * @response 503 {"message": "Could not send the code right now — please try again in a moment."}
     */
    public function resendOtp(Request $request)
    {
        return parent::resendOtp($request);
    }

    /**
     * Forgot password
     *
     * Emails a reset link (opens the website's reset page). Always the same reply, whether or not
     * the email is registered.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: buyer@example.com
     *
     * @response 200 {"message": "If an account exists for that email, a reset link has been sent."}
     */
    public function forgotPassword(Request $request)
    {
        return parent::forgotPassword($request);
    }

    /**
     * Reset password
     *
     * Only needed if the app handles the reset link itself (deep link
     * `/reset-password?token=...&email=...`); otherwise the website page does this.
     *
     * @unauthenticated
     *
     * @bodyParam token string required The token from the reset link. Example: 3f9a...
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam password string required Example: newSecret123
     * @bodyParam password_confirmation string required Example: newSecret123
     *
     * @response 200 {"message": "Your password has been reset — you can now sign in."}
     * @response 422 {"message": "This password reset token is invalid."}
     */
    public function resetPassword(Request $request)
    {
        return parent::resetPassword($request);
    }

    /**
     * Sign in
     *
     * Email + password sign-in. Returns a Bearer token for the `Authorization` header.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam password string required Example: secret123
     * @bodyParam device_name string A label for this device's token (shown nowhere yet, useful for support). Example: iPhone 15 Pro
     *
     * @response 200 scenario="Signed in" {"token": "12|9xQ...", "token_type": "Bearer", "user": {"id": 7, "name": "Sara Ahmed", "email": "buyer@example.com", "phone": "+971 50 123 4567", "location": "Dubai Marina", "avatar_url": null, "email_verified": true}}
     * @response 422 scenario="Wrong email or password" {"message": "Invalid credentials."}
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !$user->password || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return $this->tokenResponse($request, $user);
    }

    /**
     * Verify sign-up code
     *
     * Step 2 of sign-up: the 4-digit code emailed by `POST /api/auth/register`. Marks the
     * email verified and returns a Bearer token.
     *
     * @unauthenticated
     *
     * @bodyParam user_id integer required The `user_id` returned by register. Example: 7
     * @bodyParam code string required The emailed code. Example: 1234
     * @bodyParam device_name string A label for this device's token. Example: Pixel 8
     *
     * @response 200 {"token": "12|9xQ...", "token_type": "Bearer", "user": {"id": 7, "name": "Sara Ahmed", "email": "buyer@example.com", "phone": null, "location": null, "avatar_url": null, "email_verified": true}}
     * @response 422 {"message": "Invalid or expired code."}
     */
    public function verifyOtp(Request $request)
    {
        $user = $this->consumeOtp($request);
        if (!$user) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        return $this->tokenResponse($request, $user);
    }

    /**
     * Continue with Google
     *
     * Exchange the access token from the native Google Sign-In SDK for an app token. Links to an
     * existing account with the same email, or creates one. For the **User** account only —
     * agents and agencies don't have Google sign-in (show the button only on the User tab).
     *
     * @unauthenticated
     *
     * @bodyParam access_token string required The Google OAuth access token from the device SDK. Example: ya29.a0Af...
     * @bodyParam device_name string A label for this device's token. Example: iPhone 15 Pro
     *
     * @response 200 {"token": "12|9xQ...", "token_type": "Bearer", "user": {"id": 7, "name": "Sara Ahmed", "email": "sara@gmail.com", "phone": null, "location": null, "avatar_url": null, "email_verified": true}}
     * @response 422 {"message": "Google sign-in failed — please try again."}
     */
    public function google(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string|max:4096',
            'device_name' => 'nullable|string|max:100',
        ]);

        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->userFromToken($request->input('access_token'));
        } catch (\Throwable $e) {
            Log::warning('Mobile Google sign-in failed: ' . $e->getMessage());
            return response()->json(['message' => 'Google sign-in failed — please try again.'], 422);
        }

        if (!$googleUser->getEmail()) {
            return response()->json(['message' => 'Google sign-in failed — please try again.'], 422);
        }

        $user = $this->findOrCreateGoogleUser($googleUser);
        // Google has already verified this address.
        $user->email_verified_at ??= now();
        $user->save();

        return $this->tokenResponse($request, $user);
    }

    /**
     * Sign out
     *
     * Revokes the token used for this request (other devices stay signed in).
     *
     * @authenticated
     *
     * @response 200 {"message": "Signed out."}
     */
    public function logout(Request $request)
    {
        app(VisitorTracker::class)->forget($request);
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function tokenResponse(Request $request, User $user)
    {
        app(VisitorTracker::class)->customerSignedIn($request, $user);

        $token = $user->createToken($request->input('device_name') ?: 'mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => self::userPayload($user),
        ]);
    }

    /** The account fields the app shows — also returned by GET /api/customer/session. */
    public static function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'location' => $user->location,
            'avatar_url' => $user->avatar ? media_url($user->avatar) : null,
            'email_verified' => (bool) $user->email_verified_at,
        ];
    }
}
