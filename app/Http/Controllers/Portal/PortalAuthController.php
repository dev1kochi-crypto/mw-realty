<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use App\Models\Plan;
use App\Models\CmsKit\Admin;
use App\Models\CmsKit\SiteInformation;
use App\Mail\PortalAccountRegistered;
use App\Notifications\PortalRegistrationNotification;
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

        $this->notifyAdminOfRegistration($portalUser);

        return redirect()->route('portal.login')
            ->with('success', 'Account created. You can log in now to review and complete your profile — you\'ll be able to add properties once Super Admin approves your account.');
    }

    /**
     * A failed notification email must never break registration for the user — log and move on.
     * The bell/database notification (for every superadmin, independent of the configured
     * inbox above) is sent separately so one failing channel never blocks the other.
     */
    private function notifyAdminOfRegistration(PortalUser $portalUser): void
    {
        try {
            Admin::role('superadmin')->get()->each(
                fn (Admin $admin) => $admin->notify(new PortalRegistrationNotification($portalUser))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create portal registration bell notification: ' . $e->getMessage());
        }

        $adminEmail = SiteInformation::notificationEmail();
        if (!$adminEmail) {
            return;
        }

        try {
            Mail::to($adminEmail)->queue((new PortalAccountRegistered($portalUser))->afterCommit());
        } catch (\Throwable $e) {
            Log::error('Failed to send portal registration notification email: ' . $e->getMessage());
        }
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
        return view('portal.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $portalUser = PortalUser::where('email', $request->input('email'))->first();

        if (!$portalUser || !Hash::check($request->input('password'), $portalUser->password)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        // Pending and rejected accounts CAN log in — restricted to viewing/completing
        // their profile (and, once rejected, resubmitting) until Super Admin approves
        // them; only property creation is gated on approval. is_active is a hard stop.
        if (!$portalUser->is_active) {
            return back()->withErrors(['email' => 'Your account has been disabled. Contact the site administrator.'])->onlyInput('email');
        }

        Auth::guard('portal')->login($portalUser, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('password_hash_portal', $portalUser->getAuthPassword());

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
