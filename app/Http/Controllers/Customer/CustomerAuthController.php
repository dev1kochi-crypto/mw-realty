<?php

namespace App\Http\Controllers\Customer;

use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;

/**
 * Auth for the public-site "customer" (buyer/visitor) account — guard 'web', model
 * App\Models\User. Separate from Portal\PortalAuthController (agent/company, guard 'portal').
 * login()/register()/verifyOtp()/resendOtp() are all axios/JSON endpoints (see
 * resources/js/pages/Login.vue, Signup.vue, VerifyOtp.vue) — registration only grants a
 * session once the emailed OTP code is verified, not immediately on account creation.
 */
class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
        ]);

        // Not logged in yet — access is only granted once the emailed code is verified
        // (see verifyOtp()), so a registration with an email the user doesn't actually
        // control can't be used to gain access to the site. The account itself is already
        // created at this point regardless of whether the email below actually goes out —
        // a transient send failure shouldn't undo the registration, just get surfaced so the
        // user knows to hit "Resend" (which retries the same issueOtp() call).
        $sent = $this->issueOtp($user);

        return response()->json([
            'otp_required' => true,
            'user_id' => $user->id,
            'email_sent' => $sent,
        ]);
    }

    /**
     * Shared by register() and resendOtp() — emails a fresh code, then (only once that email has
     * actually gone out) stores its hash. Sent synchronously, not queued — unlike this app's
     * other transactional mail (contact/lead notifications), a code the user is waiting on right
     * now can't sit in the jobs table until something happens to run `queue:work`. Returns
     * whether the email actually went out; a transient SMTP failure (rate limit, timeout, ...) is
     * logged and reported back as false rather than bubbling up as a 500.
     *
     * The store only happens AFTER a successful send, deliberately — storing the new code first
     * and emailing second would invalidate whatever code the user already has in their inbox the
     * moment a "Resend" click's send fails (e.g. hitting Mailtrap's per-second cap), leaving them
     * unable to verify with either the old code (overwritten) or the new one (never delivered).
     */
    private function issueOtp(User $user): bool
    {
        $code = (string) random_int(1000, 9999);

        try {
            Mail::to($user->email)->send(new OtpCodeMail($user->name, $code));
        } catch (\Throwable $e) {
            Log::error('Failed to send OTP code email: ' . $e->getMessage());
            return false;
        }

        $user->forceFill([
            'otp_code' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        return true;
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'code' => 'required|string',
        ]);

        $user = User::findOrFail($request->input('user_id'));

        if (
            !$user->otp_code
            || !$user->otp_expires_at
            || $user->otp_expires_at->isPast()
            || !Hash::check($request->input('code'), $user->otp_code)
        ) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $user->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
            'email_verified_at' => now(),
        ])->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['redirect' => '/profile']);
    }

    /** Public — resources/js/pages/ForgotPassword.vue. Always a generic success message
     *  regardless of whether the email is registered, so this can't be used to enumerate
     *  accounts. Laravel's own password-reset-token table/throttle/expiry handles the rest —
     *  see config/auth.php's 'passwords.users' broker and User::sendPasswordResetNotification(). */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        PasswordBroker::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    /** Public — resources/js/pages/ResetPassword.vue. */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Your password has been reset — you can now sign in.']);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($request->input('user_id'));

        if ($user->email_verified_at) {
            return response()->json(['message' => 'This account is already verified.'], 422);
        }

        if (!$this->issueOtp($user)) {
            return response()->json(['message' => 'Could not send the code right now — please try again in a moment.'], 503);
        }

        return response()->json(['message' => 'A new code has been sent.']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !$user->password || !Hash::check($request->input('password'), $user->password)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid credentials.'], 422);
            }
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email', 'login_type');
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json(['redirect' => '/profile']);
        }

        return redirect('/profile');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /** "Continue with Google" — customer accounts only; agents/companies keep their own form-based login. */
    public function redirectToGoogle()
    {
        if (!config('services.google.client_id')) {
            return redirect('/login')->withErrors(['email' => 'Google sign-in is not available right now.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed: ' . $e->getMessage());
            return redirect('/login')->withErrors(['email' => 'Google sign-in failed — please try again.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect('/profile');
    }
}
