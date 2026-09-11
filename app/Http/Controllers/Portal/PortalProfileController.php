<?php

namespace App\Http\Controllers\Portal;

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
        $companies = PortalUser::companies()
            ->where('id', '!=', $portalUser->id)
            ->where('status', '!=', 'rejected')
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'name']);

        $documents = collect([
            ['field' => 'emirates_id_document', 'label' => 'Emirates ID', 'icon' => 'fa-id-card'],
            ['field' => 'passport_document', 'label' => 'Passport', 'icon' => 'fa-passport'],
            $portalUser->type === 'agent'
                ? ['field' => 'rera_card_document', 'label' => 'RERA Broker Card', 'icon' => 'fa-address-card']
                : ['field' => 'trade_license_document', 'label' => 'Trade License', 'icon' => 'fa-file-contract'],
            $portalUser->type === 'company'
                ? ['field' => 'rera_certificate_document', 'label' => 'RERA Registration Certificate', 'icon' => 'fa-certificate']
                : null,
        ])->filter()->map(function ($doc) use ($portalUser) {
            $doc['path'] = $portalUser->{$doc['field']};
            $doc['verification'] = $portalUser->documentStatus($doc['field']);
            return $doc;
        })->values();

        $defaultLocale = Language::active()->where('is_default', true)->value('code') ?? config('app.fallback_locale');
        $bio = $portalUser->getTranslation('bio', $defaultLocale);

        return view('portal.profile', compact('portalUser', 'companies', 'documents', 'bio'));
    }

    public function update(Request $request)
    {
        $portalUser = Auth::guard('portal')->user();

        $request->validate([
            'section' => ['required', Rule::in(['identity', 'agent', 'company', 'about'])],
            'name' => 'sometimes|required|string|max:255',
            'company_name' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'nationality' => 'sometimes|nullable|string|max:100',
            'emirates_id_no' => 'sometimes|nullable|string|max:50',
            'passport_no' => 'sometimes|nullable|string|max:50',
            'brn_number' => 'sometimes|nullable|string|max:50',
            'company_id' => ['sometimes', 'nullable', Rule::notIn([$portalUser->id]), Rule::exists('portal_users', 'id')->where('type', 'company')->where('status', 'approved')->where('is_active', true)],
            'trade_license_no' => 'sometimes|nullable|string|max:50',
            'trade_license_expiry' => 'sometimes|nullable|date',
            'orn_number' => 'sometimes|nullable|string|max:50',
            'trn_number' => 'sometimes|nullable|string|max:50',
            'authorized_signatory_name' => 'sometimes|nullable|string|max:255',
            'landline' => 'sometimes|nullable|string|max:50',
            'office_address' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string|max:2000',
            'years_of_experience' => 'sometimes|nullable|integer|min:0|max:80',
            'preferred_areas' => 'sometimes|nullable|string|max:500',
            'website' => 'sometimes|nullable|url|max:255',
            'founding_year' => 'sometimes|nullable|integer|min:1900|max:' . now()->year,
        ]);

        if (in_array($request->input('section'), ['agent', 'company'], true)) {
            abort_unless($request->input('section') === $portalUser->type, 422, 'This section does not apply to this account.');
        }
        $fieldsBySection = [
            'identity' => ['name', 'company_name', 'phone', 'nationality', 'emirates_id_no', 'passport_no'],
            'agent' => ['brn_number', 'company_id'],
            'company' => ['trade_license_no', 'trade_license_expiry', 'orn_number', 'trn_number', 'authorized_signatory_name', 'landline', 'office_address'],
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
        } else {
            $portalUser->update($request->only($fieldsBySection[$request->input('section')]));
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

            $portalUser->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * A rejected account fixes its profile, then explicitly asks for another look —
     * flips back to pending and re-notifies admin, reusing the registration email.
     */
    public function resubmit()
    {
        $portalUser = Auth::guard('portal')->user();

        if ($portalUser->status !== 'rejected') {
            return response()->json(['success' => false, 'message' => 'Only a rejected account can be resubmitted.'], 422);
        }

        $portalUser->status = 'pending';
        $portalUser->status_changed_at = now();
        $portalUser->rejection_reason = null;
        $portalUser->save();

        try {
            Admin::role('superadmin')->get()->each(
                fn (Admin $admin) => $admin->notify(new PortalRegistrationNotification($portalUser, true))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create portal resubmission bell notification: ' . $e->getMessage());
        }

        $adminEmail = SiteInformation::notificationEmail();
        if ($adminEmail) {
            try {
                Mail::to($adminEmail)->queue((new PortalAccountRegistered($portalUser, true))->afterCommit());
            } catch (\Throwable $e) {
                Log::error('Failed to send portal resubmission notification email: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true]);
    }
}
