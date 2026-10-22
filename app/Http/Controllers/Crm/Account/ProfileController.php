<?php

namespace App\Http\Controllers\Crm\Account;

use App\Mail\OtpCodeMail;
use App\Mail\PortalAccountRegistered;
use App\Models\CmsKit\Admin;
use App\Models\CmsKit\Language;
use App\Models\CmsKit\SiteInformation;
use App\Models\PortalUser;
use App\Notifications\PortalRegistrationNotification;
use App\Rules\PhoneNumber;
use App\Services\ManagedFiles;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @group CRM Account
 *
 * My Profile for the signed-in agent / company — the only place they edit their own KYC details and
 * documents (the admin-side equivalent is Super Admin only). Reachable while pending or rejected,
 * since completing / fixing the profile is exactly what unblocks approval.
 *
 * GET /profile returns the page as data: `sections` (each a list of fields with label, type, value,
 * and how to show it), `documents`, KYC progress and completeness — the web app and the mobile app
 * render the same definition. Each section is saved on its own with POST /profile (`section`).
 */
class ProfileController extends Controller
{
    /**
     * Profile sections: which account types have it, the fields it saves (per type, or "*" for all),
     * and whether a change re-opens a submitted KYC review.
     */
    public const SECTIONS = [
        // Agent
        'public' => ['types' => ['agent'], 'kyc' => false, 'fields' => ['*' => ['name', 'public_email', 'phone', 'secondary_phone', 'whatsapp_number']]],
        'compliance' => ['types' => ['agent'], 'kyc' => true, 'fields' => ['*' => ['brn_number', 'adrec_license_no', 'other_license', 'other_license_expiry', 'affiliated_brokerage', 'trade_license_no', 'trade_license_expiry', 'trn_number', 'trn_expiry']]],
        // Agency
        'contact' => ['types' => ['company'], 'kyc' => false, 'fields' => ['*' => ['phone', 'landline', 'whatsapp_number', 'public_email', 'city', 'office_address', 'website']]],
        'other' => ['types' => ['company'], 'kyc' => true, 'fields' => ['*' => ['company_name', 'name', 'trn_number', 'trn_expiry', 'authorized_signatory_name']]],
        'licenses' => ['types' => ['company'], 'kyc' => true, 'fields' => ['*' => ['trade_license_no', 'trade_license_expiry', 'orn_number', 'orn_expiry', 'adrec_license_no', 'adrec_license_expiry']]],
        // Both
        'about' => ['types' => ['agent', 'company'], 'kyc' => false, 'fields' => [
            'agent' => ['nationality', 'position', 'linkedin_url', 'website'],
            'company' => ['founding_year', 'linkedin_url'],
        ]],
        'identity' => ['types' => ['agent', 'company'], 'kyc' => true, 'fields' => ['*' => ['emirates_id_no', 'passport_no', 'passport_expiry']]],
        'seo' => ['types' => ['agent', 'company'], 'kyc' => false, 'fields' => []],
    ];

    /** Choices for an agent's "Spoken languages". */
    public const SPOKEN_LANGUAGES = [
        'English', 'Arabic', 'Hindi', 'Urdu', 'Malayalam', 'Tamil', 'Bengali', 'Tagalog', 'Persian', 'Russian',
        'French', 'German', 'Spanish', 'Italian', 'Portuguese', 'Turkish', 'Chinese', 'Japanese', 'Korean', 'Ukrainian',
    ];

    /**
     * The profile page
     *
     * Account summary, KYC progress, every section (fields with values, ready to show / edit),
     * documents, SEO metadata, what's still missing and the public page preview.
     */
    public function show()
    {
        $u = $this->portalUser();
        $isAgent = $u->type === 'agent';
        $displayName = $isAgent ? $u->name : ($u->company_name ?: $u->name);
        $bio = $u->getTranslation('bio', $this->defaultLocale());
        $sections = $this->sections($u, $bio);
        $documents = $this->documents($u);

        // How complete each section is (static fields don't count) and the profile as a whole (incl. documents).
        $isFilled = fn (array $f) => filled(is_array($f['value'] ?? null) ? array_filter($f['value']) : ($f['value'] ?? null));
        $sections = array_map(function ($s) use ($isFilled) {
            $editable = array_filter($s['fields'], fn ($f) => $f['type'] !== 'static');
            $s['done'] = count(array_filter($editable, $isFilled));
            $s['total'] = count($editable);
            foreach ($s['fields'] as &$f) {
                $f['filled'] = $isFilled($f);
            }

            return $s;
        }, $sections);
        $docsDone = count(array_filter($documents, fn ($d) => $d['submitted']));
        $completeness = (int) round((array_sum(array_column($sections, 'done')) + $docsDone)
            / max(1, array_sum(array_column($sections, 'total')) + count($documents)) * 100);

        $missingFields = collect($sections)->flatMap(fn ($s) => collect($s['fields'])
            ->filter(fn ($f) => $f['type'] !== 'static' && !$f['filled'])
            ->map(fn ($f) => ['section' => $s['key'], 'section_title' => $s['title'], 'icon' => $s['icon'], 'name' => $f['name'], 'label' => $f['label']]))->values();
        $bioText = trim(strip_tags((string) $bio));
        $meta = $u->metadata ?? [];

        return response()->json([
            'account' => [
                'id' => $u->id,
                'type' => $u->type,
                'display_name' => $displayName,
                'initials' => strtoupper(collect(preg_split('/\s+/', trim($displayName)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?',
                'email' => $u->email,
                'phone' => $u->phone,
                'status' => $u->status,
                'joined_at' => $u->created_at?->toIso8601String(),
                'avatar_url' => $u->avatar ? media_url($u->avatar) : null,
                'slug' => $u->slug,
            ],
            'stats' => [
                'properties' => $u->properties()->count(),
                'leads' => $u->leads()->count(),
                'plan' => $u->effectivePlan()?->getTranslation('name'),
                'plan_via_agency' => $u->isOnAgencyPlan(),
            ],
            'kyc' => $this->kycProgress($u, $docsDone, count($documents)),
            'completeness' => $completeness,
            'sections' => $sections,
            'documents' => $documents,
            'seo' => [
                'meta_title' => $meta['meta_title'] ?? '',
                'meta_description' => $meta['meta_description'] ?? '',
                'meta_keywords' => $meta['meta_keywords'] ?? '',
                'canonical_url' => $meta['canonical_url'] ?? '',
                'og_title' => $meta['og_title'] ?? '',
                'og_description' => $meta['og_description'] ?? '',
                'og_image_url' => !empty($meta['og_image']) ? media_url($meta['og_image']) : null,
                'canonical_placeholder' => 'https://mightywarnersrealty.com/agent-details/' . $u->slug,
            ],
            'todo' => [
                'fields' => $missingFields,
                'documents' => collect($documents)->filter(fn ($d) => !$d['submitted'])->map(fn ($d) => ['field' => $d['field'], 'label' => $d['label']])->values(),
            ],
            'public' => [
                'url' => $u->slug ? url(($isAgent ? '/agent-details/' : '/agency-details/') . $u->slug) : null,
                'live' => $u->status === 'approved' && $u->is_active,
                'subtitle' => ($isAgent ? ($u->position ?: 'Real estate agent') : 'Real estate agency') . ($u->city ? ' · ' . $u->city : ''),
                'bio' => $bioText !== '' ? Str::limit($bioText, 150) : null,
                'chips' => array_slice(($isAgent ? $u->spoken_languages : $u->preferred_areas) ?? [], 0, 4),
            ],
        ]);
    }

    /**
     * Save one section
     *
     * Multipart for `seo` (OG image). Fields per section: see GET /profile. A change to a KYC section
     * (compliance, other, licenses, identity) sends a submitted review back to "changes requested".
     *
     * @bodyParam section string required public, compliance, contact, other, licenses, about, identity or seo. Example: about
     */
    public function update(Request $request)
    {
        $portalUser = $this->portalUser();

        $request->validate([
            'section' => ['required', Rule::in(array_keys(self::SECTIONS))],
            'public_email' => 'sometimes|nullable|email|max:255',
            'secondary_phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-]{6,30}$/'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-]{6,30}$/'],
            'city' => 'sometimes|nullable|string|max:100',
            'orn_expiry' => 'sometimes|nullable|date',
            'adrec_license_expiry' => 'sometimes|nullable|date',
            'other_license' => 'sometimes|nullable|string|max:100',
            'other_license_expiry' => 'sometimes|nullable|date',
            'position' => 'sometimes|nullable|string|max:150',
            'linkedin_url' => 'sometimes|nullable|url|max:255',
            'spoken_languages' => 'sometimes|nullable|array|max:20',
            'spoken_languages.*' => ['string', Rule::in(self::SPOKEN_LANGUAGES)],
            'experience_since' => 'sometimes|nullable|integer|min:1950|max:' . now()->year,
            'bio_ar' => 'sometimes|nullable|string|max:2000',
            'name' => 'sometimes|required|string|max:255',
            'company_name' => 'sometimes|nullable|string|max:255',
            'phone' => ['sometimes', ...PhoneNumber::rules()],
            'phone_country_code' => PhoneNumber::countryCodeRules(),
            'nationality' => 'sometimes|nullable|string|max:100',
            'emirates_id_no' => 'sometimes|nullable|string|max:50',
            'passport_no' => 'sometimes|nullable|string|max:50',
            'passport_expiry' => 'sometimes|nullable|date',
            'brn_number' => 'sometimes|nullable|string|max:50',
            'affiliated_brokerage' => 'sometimes|nullable|string|max:255',
            'trade_license_no' => 'sometimes|nullable|string|max:50',
            'trade_license_expiry' => 'sometimes|nullable|date',
            'orn_number' => 'sometimes|nullable|string|max:50',
            'adrec_license_no' => 'sometimes|nullable|string|max:50',
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

        $section = $request->input('section');
        abort_unless(in_array($portalUser->type, self::SECTIONS[$section]['types'], true), 422, 'This section does not apply to this account.');
        // company_id is deliberately absent everywhere: joining/leaving an agency goes through My Agency
        // (invitation / join request + admin approval), never a free profile field.
        // affiliated_brokerage is only the brokerage named for RERA/KYC — not membership, not a plan change.
        $fields = self::SECTIONS[$section]['fields'][$portalUser->type] ?? self::SECTIONS[$section]['fields']['*'] ?? [];

        if ($section === 'about') {
            $portalUser->fill($request->only($fields));
            if ($request->has('preferred_areas')) {
                $portalUser->preferred_areas = $request->filled('preferred_areas')
                    ? array_values(array_filter(array_map('trim', explode(',', $request->input('preferred_areas')))))
                    : [];
            }
            if ($request->has('spoken_languages') || $request->has('spoken_languages_sent')) {
                $portalUser->spoken_languages = array_values(array_unique((array) $request->input('spoken_languages', [])));
            }
            // "Experience since" is what's asked; the years shown on the website follow from it.
            if ($request->has('experience_since')) {
                $portalUser->experience_since = $request->input('experience_since') ?: null;
                $portalUser->years_of_experience = $portalUser->experience_since ? max(0, now()->year - $portalUser->experience_since) : $portalUser->years_of_experience;
            }

            if ($request->has('bio') || $request->has('bio_ar')) {
                $defaultLocale = $this->defaultLocale();
                $translations = $portalUser->translations ?? [];
                if ($request->has('bio_ar')) {
                    // Written by hand: kept as is (the auto-translation only fills empty languages).
                    $translations['bio']['ar'] = (string) $request->input('bio_ar');
                }
                if ($request->has('bio')) {
                    $translations['bio'][$defaultLocale] = $request->input('bio') ?? '';
                    \App\Jobs\TranslatePortalBio::dispatch($portalUser->id, $translations['bio'][$defaultLocale], $defaultLocale)->afterCommit();
                }
                $portalUser->translations = $translations;
            }

            $portalUser->save();
        } elseif ($section === 'seo') {
            $metadata = $request->input('metadata', []);
            $existingMetadata = $portalUser->metadata ?? [];
            $files = app(ManagedFiles::class);

            if ($request->hasFile('metadata_og_image')) {
                if (!empty($existingMetadata['og_image'])) {
                    $files->delete($existingMetadata['og_image']);
                }
                $metadata['og_image'] = $files->store($request->file('metadata_og_image'), 'portal-users/metadata');
            } elseif ($request->boolean('remove_metadata_og_image') && !empty($existingMetadata['og_image'])) {
                $files->delete($existingMetadata['og_image']);
                $metadata['og_image'] = null;
            } else {
                $metadata['og_image'] = $existingMetadata['og_image'] ?? null;
            }

            $portalUser->update(['metadata' => $metadata]);
        } else {
            $data = $request->only($fields);
            if (array_key_exists('phone', $data) && filled($data['phone'])) {
                // Stored as one value with its code ("+971501234567"), as tel: / WhatsApp links expect.
                $data['phone'] = $request->input('phone_country_code', '+971') . preg_replace('/\D/', '', $data['phone']);
            }
            $portalUser->update($data);
        }

        if (self::SECTIONS[$section]['kyc'] && $portalUser->kyc_review_status === 'submitted') {
            $portalUser->forceFill(['kyc_review_status' => 'changes_requested'])->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Change login email — step 1
     *
     * Emails a 4-digit code to the NEW address; nothing changes until it is verified.
     *
     * @bodyParam new_email string required Example: new@example.com
     */
    public function requestEmailChange(Request $request)
    {
        $portalUser = $this->portalUser();

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
     * Change login email — step 2
     *
     * The code swaps the email in and signs the account out everywhere (this browser's session, and
     * every app token) — they were all authenticated under the old identity.
     *
     * @bodyParam code string required Example: 1234
     */
    public function verifyEmailChange(Request $request)
    {
        $portalUser = $this->portalUser();
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

        $portalUser->tokens()->delete();
        if ($request->hasSession()) {
            Auth::guard('portal')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['success' => true, 'redirect' => route('portal.login'), 'message' => 'Email updated — please log in again with your new email address.']);
    }

    /**
     * Profile photo / logo
     *
     * Shown on the public agent / agency pages. Multipart, at least 120 × 120 px, max 4 MB.
     *
     * @bodyParam avatar file required
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:min_width=120,min_height=120',
        ], [
            'avatar.dimensions' => 'Use an image at least 120 × 120 pixels.',
        ]);

        $portalUser = $this->portalUser();
        $files = app(ManagedFiles::class);
        if ($portalUser->avatar) {
            $files->delete($portalUser->avatar);
        }
        $portalUser->avatar = $files->store($request->file('avatar'), 'avatars');
        $portalUser->save();

        return response()->json(['success' => true, 'avatar_url' => media_url($portalUser->avatar), 'message' => $portalUser->type === 'company' ? 'Logo updated.' : 'Profile photo updated.']);
    }

    /** Remove the profile photo / logo */
    public function removeAvatar()
    {
        $portalUser = $this->portalUser();
        if ($portalUser->avatar) {
            app(ManagedFiles::class)->delete($portalUser->avatar);
            $portalUser->avatar = null;
            $portalUser->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Upload a KYC document
     *
     * JPG, PNG or PDF, max 4 MB. Replacing one clears its earlier verdict.
     *
     * @bodyParam document file required
     */
    public function uploadDocument(Request $request, string $field)
    {
        abort_unless(in_array($field, PortalUser::DOCUMENT_FIELDS, true), 404);
        $request->validate(['document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096']);

        $portalUser = $this->portalUser();
        $files = app(ManagedFiles::class);
        if ($portalUser->$field) {
            $files->delete($portalUser->$field, 'kyc');
        }
        $portalUser->$field = $files->store($request->file('document'), 'portal-kyc', 'kyc');
        $this->clearDocumentVerdict($portalUser, $field);
        $portalUser->save();

        return response()->json(['success' => true]);
    }

    /** Remove a KYC document */
    public function removeDocument(string $field)
    {
        abort_unless(in_array($field, PortalUser::DOCUMENT_FIELDS, true), 404);

        $portalUser = $this->portalUser();
        if ($portalUser->$field) {
            app(ManagedFiles::class)->delete($portalUser->$field, 'kyc');
            $portalUser->$field = null;
            $this->clearDocumentVerdict($portalUser, $field);
            $portalUser->save();
        }

        return response()->json(['success' => true]);
    }

    /** Download one of the account's own KYC documents (private storage) */
    public function document(string $field)
    {
        abort_unless(in_array($field, PortalUser::DOCUMENT_FIELDS, true), 404);
        $path = $this->portalUser()->{$field};
        abort_unless($path && str_starts_with($path, 'portal-kyc/') && Storage::disk('kyc')->exists($path), 404);

        return Storage::disk('kyc')->download($path, $field . '.' . pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Submit / resubmit KYC for approval
     *
     * Notifies Super Admin (bell + email) and the account itself.
     */
    public function submitForApproval()
    {
        $portalUser = $this->portalUser();

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

    /**
     * Brokerage suggestions
     *
     * Registered, active agencies for an agent's "Affiliated brokerage" field — 20 per page.
     * Picking one only fills the text; it never links the account to the agency.
     *
     * @queryParam q string Example: prime
     * @queryParam page integer Example: 1
     */
    public function brokerages(Request $request)
    {
        $this->portalUser();
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $page = PortalUser::companies()->approved()->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('company_name', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->orderBy('company_name')
            // `type` is needed so displayName() shows the company name, not the account holder's.
            ->paginate(20, ['id', 'type', 'name', 'company_name']);

        return response()->json([
            'results' => collect($page->items())->map(fn ($agency) => ['id' => $agency->id, 'text' => $agency->displayName()]),
            'more' => $page->hasMorePages(),
        ]);
    }

    /** Agent / company only — a Super Admin has no portal profile. */
    private function portalUser(): PortalUser
    {
        $portalUser = Auth::guard('portal')->user();
        abort_unless($portalUser, 403, 'My Profile belongs to agent and company accounts.');

        return $portalUser;
    }

    private function defaultLocale(): string
    {
        return Language::active()->where('is_default', true)->value('code') ?? config('app.fallback_locale');
    }

    /** A freshly (re-)uploaded or removed document needs another look — clear any prior verdict. */
    private function clearDocumentVerdict(PortalUser $portalUser, string $field): void
    {
        $statuses = $portalUser->document_status ?? [];
        unset($statuses[$field]);
        $portalUser->document_status = $statuses;

        if ($portalUser->kyc_review_status === 'submitted') {
            $portalUser->kyc_review_status = 'changes_requested';
        }
    }

    private function documents(PortalUser $u): array
    {
        $isAgent = $u->type === 'agent';

        return collect([
            ['field' => 'emirates_id_document', 'label' => 'Emirates ID', 'icon' => 'fa-id-card'],
            ['field' => 'passport_document', 'label' => 'Passport', 'icon' => 'fa-passport'],
            $isAgent
                ? ['field' => 'rera_card_document', 'label' => 'RERA Broker Card', 'icon' => 'fa-address-card']
                : ['field' => 'trade_license_document', 'label' => 'Trade License', 'icon' => 'fa-file-contract'],
            $isAgent ? ['field' => 'trade_license_document', 'label' => 'Trade License', 'icon' => 'fa-file-contract'] : null,
            ['field' => 'rera_certificate_document', 'label' => 'RERA Registration Certificate', 'icon' => 'fa-certificate'],
        ])->filter()->map(fn ($doc) => $doc + [
            'submitted' => (bool) $u->{$doc['field']},
            'verification' => $u->documentStatus($doc['field']),
        ])->values()->all();
    }

    /** "Where am I" in KYC — null once approved (the card isn't shown then). */
    private function kycProgress(PortalUser $u, int $docsDone, int $docsTotal): ?array
    {
        $canSubmit = $u->status !== 'approved' && (in_array($u->kyc_review_status, ['draft', 'changes_requested'], true) || !$u->kyc_user_submitted_at);
        $isResubmission = $u->kyc_review_status === 'changes_requested' || $u->kyc_user_submitted_at !== null;
        $base = ['can_submit' => $canSubmit, 'is_resubmission' => $isResubmission];
        if ($u->status === 'approved') {
            return null;
        }

        $isRejected = $u->status === 'rejected';
        $changesRequested = !$isRejected && $u->kyc_review_status === 'changes_requested';
        $inReview = !$isRejected && !$changesRequested && $u->kyc_review_status === 'submitted' && $u->kyc_user_submitted_at;
        // Which of the 4 steps we're on: 1 prepare, 2 submit, 3 review (4 = approved, never shown here).
        $current = $inReview ? 3 : ($docsDone >= $docsTotal && $docsTotal > 0 ? 2 : 1);
        $state = fn (int $step) => match (true) {
            ($isRejected || $changesRequested) && $step === 1 => 'is-problem',
            $step < $current => 'is-done',
            $step === $current => 'is-current',
            default => '',
        };
        [$tone, $icon, $title, $text] = match (true) {
            $isRejected => ['tone-red', 'fa-ban', 'Your application needs changes', 'Update the details below, then resubmit for approval. CRM access unlocks once you are approved.'],
            $changesRequested => ['tone-amber', 'fa-comment-medical', 'Our team asked for a few updates', 'Make the changes below, then resubmit for approval.'],
            $inReview => ['tone-blue', 'fa-hourglass-half', 'KYC submitted — waiting for review', 'Our team is reviewing your documents. Leads, Reports and listings unlock as soon as you are approved.'],
            default => ['tone-amber', 'fa-clipboard-check', 'Finish your KYC to unlock the CRM', 'Complete your details and upload your documents, then submit them for approval.'],
        };

        return $base + [
            'tone' => $tone, 'icon' => $icon, 'title' => $title, 'text' => $text,
            'steps' => [$state(1), $state(2), $state(3)],
            'docs_done' => $docsDone,
            'docs_total' => $docsTotal,
            'submitted_at' => $u->kyc_user_submitted_at?->toIso8601String(),
            'in_review' => (bool) $inReview,
            'rejection_reason' => $isRejected ? $u->rejection_reason : null,
            'review_note' => $changesRequested ? $u->kyc_review_note : null,
        ];
    }

    /** The profile sections for this account type, as the page shows them (see the class doc). */
    private function sections(PortalUser $u, ?string $bio): array
    {
        $isAgent = $u->type === 'agent';
        $areas = $u->preferred_areas ? implode(', ', $u->preferred_areas) : '';
        $bioAr = $u->translations['bio']['ar'] ?? '';

        $defs = $isAgent ? [
            'public' => ['Public details', 'fa-id-card', 'How buyers see and reach you on your listings and agent page.', [
                ['name' => 'name', 'label' => 'Displayed name', 'value' => $u->name, 'col' => 12],
                ['name' => 'public_email', 'label' => 'Public email', 'type' => 'email', 'value' => $u->public_email, 'col' => 6, 'help' => 'Leave empty to show your login email.'],
                ['name' => 'phone', 'label' => 'Public number', 'type' => 'phone', 'value' => $u->phone, 'col' => 6],
                ['name' => 'secondary_phone', 'label' => 'Secondary phone number', 'type' => 'tel', 'value' => $u->secondary_phone, 'col' => 6, 'placeholder' => '+971 50 123 4567'],
                ['name' => 'whatsapp_number', 'label' => 'WhatsApp', 'type' => 'tel', 'value' => $u->whatsapp_number, 'col' => 6, 'placeholder' => '+971 50 123 4567'],
            ]],
            'compliance' => ['Compliance', 'fa-shield-halved', 'Your broker licenses — shown on your listings and checked with your permits.', [
                ['name' => 'brn_number', 'label' => 'Dubai Broker License (BRN)', 'value' => $u->brn_number, 'col' => 6],
                ['name' => 'adrec_license_no', 'label' => 'Abu Dhabi Broker License (BLN)', 'value' => $u->adrec_license_no, 'col' => 6],
                ['name' => 'other_license', 'label' => 'Other license', 'value' => $u->other_license, 'col' => 6],
                ['name' => 'other_license_expiry', 'label' => 'Other license expiry date', 'type' => 'date', 'expiry' => true, 'value' => $u->other_license_expiry, 'col' => 6],
                ['name' => 'affiliated_brokerage', 'label' => 'Affiliated brokerage', 'type' => 'brokerage', 'value' => $u->affiliated_brokerage, 'col' => 12,
                    'note_html' => $u->company
                        ? '<i class="fas fa-building me-1"></i>Agency member: <strong>' . e($u->company->displayName()) . '</strong> (their plan applies)'
                        : '<i class="fas fa-user me-1"></i>Independent agent on your own plan'],
                ['name' => 'trade_license_no', 'label' => 'Trade License No.', 'value' => $u->trade_license_no, 'col' => 6, 'label_hint' => 'independent agents'],
                ['name' => 'trade_license_expiry', 'label' => 'Trade License Expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->trade_license_expiry, 'col' => 6],
                ['name' => 'trn_number', 'label' => 'TRN (VAT)', 'value' => $u->trn_number, 'col' => 6],
                ['name' => 'trn_expiry', 'label' => 'TRN Expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->trn_expiry, 'col' => 6],
            ]],
            'about' => ['About me', 'fa-user', 'Shown on your public agent page. Write the description in your own language — it\'s translated automatically for other site languages unless you write the Arabic one yourself.', [
                ['name' => 'experience_since', 'label' => 'Experience since', 'type' => 'year', 'value' => $u->experience_since, 'col' => 4,
                    'display' => $u->experience_since ? $u->experience_since . ' · ' . max(0, now()->year - $u->experience_since) . ' years' : null],
                ['name' => 'spoken_languages', 'label' => 'Spoken languages', 'type' => 'multiselect', 'options' => self::SPOKEN_LANGUAGES, 'value' => $u->spoken_languages ?? [], 'col' => 8],
                ['name' => 'nationality', 'label' => 'Nationality', 'value' => $u->nationality, 'col' => 4],
                ['name' => 'position', 'label' => 'Position', 'value' => $u->position, 'col' => 4, 'placeholder' => 'e.g. Senior Property Consultant'],
                ['name' => 'linkedin_url', 'label' => 'LinkedIn', 'type' => 'url', 'value' => $u->linkedin_url, 'col' => 4, 'placeholder' => 'https://linkedin.com/in/…'],
                ['name' => 'website', 'label' => 'Website', 'type' => 'url', 'value' => $u->website, 'col' => 4, 'placeholder' => 'https://'],
                ['name' => 'preferred_areas', 'label' => 'Preferred areas', 'value' => $areas, 'col' => 8, 'label_hint' => 'comma separated', 'placeholder' => 'Downtown Dubai, Business Bay'],
                ['name' => 'bio', 'label' => 'Description', 'type' => 'textarea', 'value' => $bio, 'col' => 12],
                ['name' => 'bio_ar', 'label' => 'Arabic description', 'type' => 'textarea', 'value' => $bioAr, 'col' => 12, 'rtl' => true, 'placeholder' => 'التفاصيل باللغة العربية'],
            ]],
        ] : [
            'contact' => ['Contact information', 'fa-address-book', 'How buyers and partners reach your agency.', [
                ['name' => 'phone', 'label' => 'Phone number', 'type' => 'phone', 'value' => $u->phone, 'col' => 4],
                ['name' => 'landline', 'label' => 'Landline', 'value' => $u->landline, 'col' => 4],
                ['name' => 'whatsapp_number', 'label' => 'WhatsApp', 'type' => 'tel', 'value' => $u->whatsapp_number, 'col' => 4, 'placeholder' => '+971 50 123 4567'],
                ['name' => 'public_email', 'label' => 'Public email', 'type' => 'email', 'value' => $u->public_email, 'col' => 4, 'help' => 'Leave empty to show the login email.'],
                ['name' => 'city', 'label' => 'City', 'value' => $u->city, 'col' => 4, 'placeholder' => 'Dubai'],
                ['name' => 'website', 'label' => 'Website URL', 'type' => 'url', 'value' => $u->website, 'col' => 4, 'placeholder' => 'https://'],
                ['name' => 'office_address', 'label' => 'Address', 'value' => $u->office_address, 'col' => 12, 'placeholder' => 'Office, building, area, city, PO Box'],
            ]],
            'other' => ['Other information', 'fa-circle-info', 'Your account and billing details.', [
                ['name' => 'account_no', 'label' => 'Account number', 'type' => 'static', 'value' => str_pad((string) $u->id, 6, '0', STR_PAD_LEFT), 'locked_help' => 'Your MW Realty account number — fixed.'],
                ['name' => 'client_type', 'label' => 'Client type', 'type' => 'static', 'value' => 'Broker', 'locked_help' => 'Set from your account type.'],
                ['name' => 'login_email', 'label' => 'Default email address', 'type' => 'static', 'value' => $u->email, 'locked_help' => 'Your login email — changed with a code sent to the new address.', 'locked_action' => 'email'],
                ['name' => 'company_name', 'label' => 'Display client name', 'value' => $u->company_name, 'col' => 6],
                ['name' => 'name', 'label' => 'Contact person', 'value' => $u->name, 'col' => 6],
                ['name' => 'trn_number', 'label' => 'VAT number (TRN)', 'value' => $u->trn_number],
                ['name' => 'trn_expiry', 'label' => 'TRN expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->trn_expiry],
                ['name' => 'authorized_signatory_name', 'label' => 'Authorized signatory', 'value' => $u->authorized_signatory_name],
            ]],
            'licenses' => ['Corporate licenses', 'fa-file-contract', 'The ORN validates Dubai (RERA) permits, the ADREC number Abu Dhabi / Al Ain permits.', [
                ['name' => 'trade_license_no', 'label' => 'Trade license number', 'value' => $u->trade_license_no, 'col' => 6],
                ['name' => 'trade_license_expiry', 'label' => 'Trade license expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->trade_license_expiry, 'col' => 6],
                ['name' => 'orn_number', 'label' => 'ORN number', 'value' => $u->orn_number, 'col' => 6, 'label_hint' => 'RERA, Dubai'],
                ['name' => 'orn_expiry', 'label' => 'ORN expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->orn_expiry, 'col' => 6],
                ['name' => 'adrec_license_no', 'label' => 'ADREC brokerage registration no.', 'value' => $u->adrec_license_no, 'col' => 6, 'label_hint' => 'Abu Dhabi'],
                ['name' => 'adrec_license_expiry', 'label' => 'ADREC expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->adrec_license_expiry, 'col' => 6],
            ]],
            'about' => ['About', 'fa-globe', 'Shown on your public agency page. Write the description in your own language — it\'s translated automatically for other site languages unless you write the Arabic one yourself.', [
                ['name' => 'founding_year', 'label' => 'Established', 'type' => 'year', 'value' => $u->founding_year, 'col' => 4],
                ['name' => 'linkedin_url', 'label' => 'LinkedIn', 'type' => 'url', 'value' => $u->linkedin_url, 'col' => 4, 'placeholder' => 'https://linkedin.com/company/…'],
                ['name' => 'preferred_areas', 'label' => 'Service areas', 'value' => $areas, 'col' => 4, 'label_hint' => 'comma separated', 'placeholder' => 'Downtown Dubai, Business Bay'],
                ['name' => 'bio', 'label' => 'Description', 'type' => 'textarea', 'value' => $bio, 'col' => 12],
                ['name' => 'bio_ar', 'label' => 'Arabic description', 'type' => 'textarea', 'value' => $bioAr, 'col' => 12, 'rtl' => true, 'placeholder' => 'التفاصيل باللغة العربية'],
            ]],
        ];
        $defs['identity'] = [$isAgent ? 'Identity' : 'Signatory identity', 'fa-id-badge', 'Private — only used by MW Realty to verify your account, never shown publicly.', [
            ['name' => 'emirates_id_no', 'label' => 'Emirates ID No.', 'value' => $u->emirates_id_no],
            ['name' => 'passport_no', 'label' => 'Passport No.', 'value' => $u->passport_no],
            ['name' => 'passport_expiry', 'label' => 'Passport expiry', 'type' => 'date', 'expiry' => true, 'value' => $u->passport_expiry],
        ]];

        $sections = [];
        foreach ($defs as $key => [$title, $icon, $hint, $fields]) {
            $sections[] = ['key' => $key, 'title' => $title, 'icon' => $icon, 'hint' => $hint, 'fields' => array_map(fn ($f) => $this->field($f), $fields)];
        }

        return $sections;
    }

    /** One field, JSON-ready: dates as Y-m-d, `display` = the read-only text, expiry badge, split phone. */
    private function field(array $f): array
    {
        $type = $f['type'] ?? 'text';
        $value = $f['value'] ?? null;
        $badge = null;

        if ($value instanceof \DateTimeInterface) {
            if (!empty($f['expiry'])) {
                $days = (int) today()->diffInDays(Carbon::parse($value), false);
                $badge = match (true) {
                    $days < 0 => ['is-bad', 'Expired'],
                    $days <= 30 => ['is-warn', $days === 0 ? 'Expires today' : "Expires in {$days} day" . ($days === 1 ? '' : 's')],
                    default => ['is-ok', 'Valid'],
                };
            }
            $display = $value->format('d M Y');
            $value = $value->format('Y-m-d');
        } else {
            $display = is_array($value) ? implode(', ', $value) : $value;
        }
        if (array_key_exists('display', $f)) {
            $display = $f['display'];
        }

        $out = [
            'name' => $f['name'],
            'label' => $f['label'],
            'type' => $type,
            'col' => $f['col'] ?? 4,
            'value' => $value,
            'display' => $display,
            'badge' => $badge,
        ] + array_intersect_key($f, array_flip(['options', 'placeholder', 'help', 'rtl', 'note_html', 'label_hint', 'locked_help', 'locked_action']));

        if ($type === 'phone') {
            [$code, $number] = PhoneNumber::split($value);
            $out['phone_code'] = $code ?? '+971';
            $out['phone_number'] = $number;
        }

        return $out;
    }
}
