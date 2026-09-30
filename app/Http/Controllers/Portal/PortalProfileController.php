<?php

namespace App\Http\Controllers\Portal;

use App\Mail\OtpCodeMail;
use App\Mail\PortalAccountRegistered;
use App\Models\CmsKit\Admin;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SiteInformation;
use App\Models\PortalUser;
use App\Notifications\PortalRegistrationNotification;
use App\Services\AutoTranslator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Self-service profile for the logged-in Agent/Company — the only place they can
 * edit their own KYC details and documents (the equivalent admin-side page under
 * /admin/portal-accounts/{id} is Super Admin only). Available even while pending
 * or rejected, since completing/fixing the profile is exactly what unblocks approval.
 */
class PortalProfileController extends Controller
{
    public function edit()
    {
        $portalUser = Auth::guard('portal')->user();

        $documents = collect([
            ['field' => 'emirates_id_document', 'label' => 'Emirates ID', 'icon' => 'fa-id-card'],
            ['field' => 'passport_document', 'label' => 'Passport', 'icon' => 'fa-passport'],
            $portalUser->type === 'agent'
                ? ['field' => 'rera_card_document', 'label' => 'RERA Broker Card', 'icon' => 'fa-address-card']
                : ['field' => 'trade_license_document', 'label' => 'Trade License', 'icon' => 'fa-file-contract'],
            $portalUser->type === 'agent'
                ? ['field' => 'trade_license_document', 'label' => 'Trade License', 'icon' => 'fa-file-contract']
                : null,
            $portalUser->type === 'company'
                ? ['field' => 'rera_certificate_document', 'label' => 'RERA Registration Certificate', 'icon' => 'fa-certificate']
            : null,
            $portalUser->type === 'agent'
                ? ['field' => 'rera_certificate_document', 'label' => 'RERA Registration Certificate', 'icon' => 'fa-certificate']
                : null,
        ])->filter()->map(function ($doc) use ($portalUser) {
            $doc['path'] = $portalUser->{$doc['field']};
            $doc['verification'] = $portalUser->documentStatus($doc['field']);
            return $doc;
        })->values();

        $defaultLocale = Language::active()->where('is_default', true)->value('code') ?? config('app.fallback_locale');
        $bio = $portalUser->getTranslation('bio', $defaultLocale);

        return view('portal.profile', compact('portalUser', 'documents', 'bio'));
    }

    public function update(Request $request)
    {
        $portalUser = Auth::guard('portal')->user();

        $request->validate([
            'section' => ['required', Rule::in(['identity', 'agent', 'company', 'about', 'seo'])],
            'name' => 'sometimes|required|string|max:255',
            'company_name' => 'sometimes|nullable|string|max:255',
            'phone' => ['sometimes', ...\App\Rules\PhoneNumber::rules()],
            'phone_country_code' => \App\Rules\PhoneNumber::countryCodeRules(),
            'nationality' => 'sometimes|nullable|string|max:100',
            'emirates_id_no' => 'sometimes|nullable|string|max:50',
            'passport_no' => 'sometimes|nullable|string|max:50',
            'passport_expiry' => 'sometimes|nullable|date',
            'brn_number' => 'sometimes|nullable|string|max:50',
            'affiliated_brokerage' => 'sometimes|nullable|string|max:255',
            'trade_license_no' => 'sometimes|nullable|string|max:50',
            'trade_license_expiry' => 'sometimes|nullable|date',
            'orn_number' => 'sometimes|nullable|string|max:50',
            'trn_number' => 'sometimes|nullable|string|max:50',
            'trn_expiry' => 'sometimes|nullable|date',
            'authorized_signatory_name' => 'sometimes|nullable|string|max:255',
            'landline' => 'sometimes|nullable|string|max:50',
            'office_address' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string|max:2000',
            'years_of_experience' => 'sometimes|nullable|integer|min:0|max:80',
            'preferred_areas' => 'sometimes|nullable|string|max:500',
            'website' => 'sometimes|nullable|url|max:255',
            'founding_year' => 'sometimes|nullable|integer|min:1900|max:' . now()->year,
            'metadata' => 'sometimes|nullable|array',
            'metadata.meta_title' => 'nullable|string|max:255',
            'metadata.meta_description' => 'nullable|string|max:500',
            'metadata.meta_keywords' => 'nullable|string|max:500',
            'metadata.canonical_url' => 'nullable|url|max:2048',
            'metadata.og_title' => 'nullable|string|max:255',
            'metadata.og_description' => 'nullable|string|max:500',
            'metadata.other_meta_tags' => 'nullable|string',
            'metadata_og_image' => 'nullable|image|max:4096',
            'remove_metadata_og_image' => 'nullable|boolean',
        ]);

        if (in_array($request->input('section'), ['agent', 'company'], true)) {
            abort_unless($request->input('section') === $portalUser->type, 422, 'This section does not apply to this account.');
        }
        $fieldsBySection = [
            'identity' => ['name', 'company_name', 'phone', 'nationality', 'emirates_id_no', 'passport_no', 'passport_expiry'],
            // company_id is deliberately absent: joining/leaving an agency goes through My Agency
            // (invitation / join request + admin approval), never a free profile field.
            // affiliated_brokerage is only the brokerage named for RERA/KYC — not membership, not a plan change.
            'agent' => ['brn_number', 'affiliated_brokerage', 'trade_license_no', 'trade_license_expiry', 'trn_number', 'trn_expiry'],
            'company' => ['trade_license_no', 'trade_license_expiry', 'orn_number', 'trn_number', 'trn_expiry', 'authorized_signatory_name', 'landline', 'office_address'],
            'about' => ['years_of_experience', 'website', 'founding_year'],
        ];

        if ($request->input('section') === 'about') {
            $portalUser->fill($request->only($fieldsBySection['about']));
            $portalUser->preferred_areas = $request->filled('preferred_areas')
                ? array_values(array_filter(array_map('trim', explode(',', $request->input('preferred_areas')))))
                : [];

            if ($request->has('bio')) {
                $defaultLocale = Language::active()->where('is_default', true)->value('code') ?? config('app.fallback_locale');
                $translations = $portalUser->translations ?? [];
                $translations['bio'][$defaultLocale] = $request->input('bio') ?? '';
                $portalUser->translations = $translations;
                \App\Jobs\TranslatePortalBio::dispatch($portalUser->id, $translations['bio'][$defaultLocale], $defaultLocale)->afterCommit();
            }

            $portalUser->save();
        } elseif ($request->input('section') === 'seo') {
            $metadata = $request->input('metadata', []);
            $existingMetadata = $portalUser->metadata ?? [];

            if ($request->hasFile('metadata_og_image')) {
                if (!empty($existingMetadata['og_image'])) {
                    app(\App\Services\ManagedFiles::class)->delete($existingMetadata['og_image']);
                }
                $metadata['og_image'] = app(\App\Services\ManagedFiles::class)->store($request->file('metadata_og_image'), 'portal-users/metadata');
            } elseif ($request->boolean('remove_metadata_og_image') && !empty($existingMetadata['og_image'])) {
                app(\App\Services\ManagedFiles::class)->delete($existingMetadata['og_image']);
                $metadata['og_image'] = null;
            } else {
                $metadata['og_image'] = $existingMetadata['og_image'] ?? null;
            }

            $portalUser->update(['metadata' => $metadata]);
        } else {
            $data = $request->only($fieldsBySection[$request->input('section')]);
            if (array_key_exists('phone', $data) && filled($data['phone'])) {
                // Stored as one value with its code ("+971501234567"), as tel: / WhatsApp links expect.
                $data['phone'] = $request->input('phone_country_code', '+971') . preg_replace('/\D/', '', $data['phone']);
            }
            $portalUser->update($data);
        }

        if (in_array($request->input('section'), ['identity', 'agent', 'company'], true)
            && $portalUser->kyc_review_status === 'submitted') {
            $portalUser->forceFill(['kyc_review_status' => 'changes_requested'])->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Step 1 of changing the login email: stage the new address and email a code to IT (not the
     * current address) — proves the account actually owns the new inbox before anything changes.
     * The real `email` column is untouched until verifyEmailChange() succeeds.
     */
    public function requestEmailChange(Request $request)
    {
        $portalUser = Auth::guard('portal')->user();

        $request->validate([
            'new_email' => ['required', 'email', Rule::unique('portal_users', 'email')->ignore($portalUser->id)],
        ]);

        $newEmail = $request->input('new_email');
        $code = (string) random_int(1000, 9999);

        try {
            Mail::to($newEmail)->send(new OtpCodeMail($portalUser->displayName(), $code));
        } catch (\Throwable $e) {
            Log::error('Failed to send email-change OTP: ' . $e->getMessage());
            return response()->json(['message' => 'Could not send the code right now — please try again in a moment.'], 503);
        }

        $portalUser->forceFill([
            'pending_email' => $newEmail,
            'otp_code' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        return response()->json(['success' => true, 'message' => 'A verification code was sent to ' . $newEmail . '.']);
    }

    /**
     * Step 2: verifying the code swaps the real email in, then forces a fresh login — the
     * session and every other device's session were authenticated under the old identity, and
     * EnforceAccountSecurity's password-fingerprint check doesn't cover an email change, so this
     * logout is the actual enforcement point.
     */
    public function verifyEmailChange(Request $request)
    {
        $portalUser = Auth::guard('portal')->user();

        $request->validate(['code' => 'required|string']);

        if (
            !$portalUser->pending_email
            || !$portalUser->otp_code
            || !$portalUser->otp_expires_at
            || $portalUser->otp_expires_at->isPast()
            || !Hash::check($request->input('code'), $portalUser->otp_code)
        ) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $portalUser->forceFill([
            'email' => $portalUser->pending_email,
            'pending_email' => null,
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'redirect' => route('portal.login'), 'message' => 'Email updated — please log in again with your new email address.']);
    }

    /**
     * Profile photo (agent) / logo (agency) — shown on the public agent / agency pages and the
     * property page's contact card. Public media (Cloudinary when configured), not a KYC document.
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:min_width=120,min_height=120',
        ], [
            'avatar.dimensions' => 'Use an image at least 120 × 120 pixels.',
        ]);

        $portalUser = Auth::guard('portal')->user();
        $files = app(\App\Services\ManagedFiles::class);
        if ($portalUser->avatar) {
            $files->delete($portalUser->avatar);
        }
        $portalUser->avatar = $files->store($request->file('avatar'), 'avatars');
        $portalUser->save();

        return response()->json(['success' => true, 'avatar_url' => media_url($portalUser->avatar), 'message' => $portalUser->type === 'company' ? 'Logo updated.' : 'Profile photo updated.']);
    }

    public function removeAvatar()
    {
        $portalUser = Auth::guard('portal')->user();
        if ($portalUser->avatar) {
            app(\App\Services\ManagedFiles::class)->delete($portalUser->avatar);
            $portalUser->avatar = null;
            $portalUser->save();
        }

        return response()->json(['success' => true]);
    }

    public function uploadDocument(Request $request, $field)
    {
        abort_unless(in_array($field, PortalUser::DOCUMENT_FIELDS, true), 404);

        $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $portalUser = Auth::guard('portal')->user();

        if ($portalUser->$field) {
            app(\App\Services\ManagedFiles::class)->delete($portalUser->$field, 'kyc');
        }

        $file = $request->file('document');
        $portalUser->$field = app(\App\Services\ManagedFiles::class)->store($file, 'portal-kyc', 'kyc');

        // A freshly re-uploaded document needs another look — clear any prior verdict.
        $statuses = $portalUser->document_status ?? [];
        unset($statuses[$field]);
        $portalUser->document_status = $statuses;

        if ($portalUser->kyc_review_status === 'submitted') {
            $portalUser->kyc_review_status = 'changes_requested';
        }

        $portalUser->save();

        return response()->json(['success' => true, 'path' => route('portal.profile.document', $field)]);
    }

    public function removeDocument($field)
    {
        abort_unless(in_array($field, PortalUser::DOCUMENT_FIELDS, true), 404);

        $portalUser = Auth::guard('portal')->user();

        if ($portalUser->$field) {
            app(\App\Services\ManagedFiles::class)->delete($portalUser->$field, 'kyc');
            $portalUser->$field = null;

            $statuses = $portalUser->document_status ?? [];
            unset($statuses[$field]);
            $portalUser->document_status = $statuses;

            if ($portalUser->kyc_review_status === 'submitted') {
                $portalUser->kyc_review_status = 'changes_requested';
            }

            $portalUser->save();
        }

        return response()->json(['success' => true]);
    }

    /** The agent/company explicitly submits or resubmits their completed KYC for review. */
    public function submitForApproval()
    {
        $portalUser = Auth::guard('portal')->user();

        if ($portalUser->status === 'approved') {
            return response()->json(['success' => false, 'message' => 'This account is already approved.'], 422);
        }

        $legacyPendingNeedsSubmission = $portalUser->status === 'pending'
            && $portalUser->kyc_review_status === 'submitted'
            && !$portalUser->kyc_user_submitted_at;

        if (!in_array($portalUser->kyc_review_status, ['draft', 'changes_requested'], true)
            && !$legacyPendingNeedsSubmission) {
            return response()->json(['success' => false, 'message' => 'Your KYC is already waiting for review.'], 422);
        }

        $isResubmission = $portalUser->kyc_review_status === 'changes_requested'
            || $portalUser->status === 'rejected'
            || $portalUser->kyc_user_submitted_at !== null;

        $portalUser->forceFill([
            'status' => 'pending',
            'status_changed_at' => now(),
            'rejection_reason' => null,
            'kyc_review_status' => 'submitted',
            'kyc_submitted_at' => now(),
            'kyc_user_submitted_at' => now(),
            'kyc_review_note' => null,
        ])->save();

        try {
            Admin::role('superadmin')->get()->each(
                fn (Admin $admin) => $admin->notify(new PortalRegistrationNotification($portalUser, $isResubmission))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create portal KYC submission bell notification: ' . $e->getMessage());
        }

        $adminEmail = SiteInformation::notificationEmail();
        if ($adminEmail) {
            try {
                Mail::to($adminEmail)->queue((new PortalAccountRegistered($portalUser, $isResubmission))->afterCommit());
            } catch (\Throwable $e) {
                Log::error('Failed to send portal KYC submission email: ' . $e->getMessage());
            }
        }

        // And an in-app "we got it" to the agent / agency themselves (no email — only admin is emailed).
        try {
            $portalUser->notify(new \App\Notifications\PortalKycSubmittedNotification($isResubmission));
        } catch (\Throwable $e) {
            Log::error('Failed to create KYC submitted bell notification: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }
}
