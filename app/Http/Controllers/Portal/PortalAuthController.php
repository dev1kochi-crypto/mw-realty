<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use App\Models\Plan;
use App\Mail\OtpCodeMail;
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
            'phone' => 'nullable|string|max:50',
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
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'status' => 'pending',
            'kyc_review_status' => 'draft',
            'status_changed_at' => now(),
            'plan_id' => Plan::defaultFree()?->id,

            'nationality' => $request->input('nationality'),
            'emirates_id_no' => $request->input('emirates_id_no'),
            'passport_no' => $request->input('passport_no'),

            'brn_number' => $request->input('type') === 'agent' ? $request->input('brn_number') : null,
            'company_id' => $request->input('type') === 'agent' ? $request->input('company_id') : null,

            'trade_license_no' => $request->input('type') === 'company' ? $request->input('trade_license_no') : null,
            'trade_license_expiry' => $request->input('type') === 'company' ? $request->input('trade_license_expiry') : null,
            'orn_number' => $request->input('type') === 'company' ? $request->input('orn_number') : null,
            'office_address' => $request->input('type') === 'company' ? $request->input('office_address') : null,
            'trn_number' => $request->input('type') === 'company' ? $request->input('trn_number') : null,
            'authorized_signatory_name' => $request->input('type') === 'company' ? $request->input('authorized_signatory_name') : null,
            'landline' => $request->input('type') === 'company' ? $request->input('landline') : null,
        ]);

        $this->storeKycDocuments($request, $portalUser);

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
        $code = (string) random_int(1000, 9999);

        try {
            Mail::to($portalUser->email)->send(new OtpCodeMail($portalUser->displayName(), $code));
        } catch (\Throwable $e) {
            Log::error('Failed to send portal OTP code email: ' . $e->getMessage());
            return false;
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
        ])->save();

        Auth::guard('portal')->login($portalUser);
        $request->session()->regenerate();
        $request->session()->put('password_hash_portal', $portalUser->getAuthPassword());

        return response()->json(['redirect' => route('portal.dashboard')]);
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

        Auth::guard('portal')->login($portalUser, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('password_hash_portal', $portalUser->getAuthPassword());

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('portal.dashboard')]);
        }

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
