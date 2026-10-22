<?php

namespace App\Services;

use App\Models\CmsKit\Admin;
use App\Models\CmsKit\SiteInformation;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyComplianceLog;
use App\Notifications\ListingComplianceNotification;
use App\Notifications\ListingReviewRequestedNotification;
use App\Mail\ListingComplianceMail;
use App\Mail\ListingReviewRequestedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Listing compliance: a listing may be on the website only while the advertising permit its emirate
 * needs (App\Support\PermitRules — DLD / RERA, DTCM, ADREC, or none for DIFC / JAFZA and the Northern
 * Emirates outside Al Ain) is verified — by the agency / agent with Validate in the property form
 * (App\Services\Permits\PermitVerifier) — and not expired. The state is worked out on every save
 * (stateFor()). Super Admin approves a listing on Listing Permits only where approval applies
 * (PermitRules::needsApproval: LISTING_SUPERADMIN_APPROVAL on → every listing; off → only DTCM and
 * None, which can't be validated online), and can take any listing down.
 *
 * The listing's `status` (website on/off) can only be true while compliance_status is APPROVED
 * ("Verified") and the permit hasn't expired (Property::canGoLive()). Expired permits are taken off
 * the site daily by `properties:expire-permits`.
 */
class ListingComplianceService
{
    /** Fields the permit covers — a change re-works out the permit state on save. */
    public const MATERIAL_FIELDS = [
        'emirate', 'permit_type', 'permit_city',
        'permit_number', 'permit_expires_at', 'permit_qr', 'permit_verification_url',
        'price', 'listing_type', 'property_type', 'bedrooms', 'sqft',
    ];

    public const EXPIRY_REMINDER_DAYS = 7;

    public function __construct(private readonly ManagedFiles $files)
    {
    }

    /** Snapshot of the material fields, taken before an update to detect what changed. */
    public function snapshot(Property $property): array
    {
        return collect(self::MATERIAL_FIELDS)->mapWithKeys(fn ($field) => [$field => $this->normalise($property->{$field})])->all();
    }

    /** Saves the permit QR (public — shown on the website). */
    public function storeUploads(Request $request, Property $property): void
    {
        $updates = [];
        if ($request->hasFile('permit_qr')) {
            $this->files->delete($property->permit_qr);
            $updates['permit_qr'] = $this->files->store($request->file('permit_qr'), 'properties/permits');
        }
        if ($updates) {
            $property->update($updates);
        }
    }

    /** Permit details still missing (empty = complete, ready to be validated). */
    public function missingItems(Property $property): array
    {
        $missing = [];
        $type = $property->permit_type;
        if (!$property->emirate) {
            $missing[] = 'Emirate';
        } elseif (!$type) {
            $missing[] = 'Permit details (choose the city)';
        }
        // DIFC / JAFZA and Northern Emirates outside Al Ain need no advertising permit.
        if (\App\Support\PermitRules::requiresPermit($type)) {
            $issuer = \App\Support\PermitRules::issuer($type);
            if (!$property->permit_number) {
                $missing[] = "{$issuer} advertising permit number";
            }
            if (!$property->permit_expires_at) {
                $missing[] = 'Permit expiry date';
            } elseif ($property->permit_expires_at->lt(today())) {
                $missing[] = 'A valid permit — the recorded one has expired';
            }
            if (\App\Support\PermitRules::requiresQr($type) && !$property->permit_qr) {
                $missing[] = $type === 'adrec' ? 'Permit QR code (ADREC)' : 'Permit QR code (Madmoun)';
            }
        }
        return $missing;
    }

    /**
     * The permit state a listing's details put it in:
     *   expired permit → EXPIRED · details incomplete → DRAFT · Super Admin approval applies
     *   (PermitRules::needsApproval) → AWAITING APPROVAL (pending) · no permit needed or permit verified
     *   (Validate) → VERIFIED (may be live) · otherwise → NOT VERIFIED (never on the website).
     *   Listings Super Admin approved before validation existed keep their verification ("legacy").
     */
    public function stateFor(Property $property): string
    {
        $needsPermit = \App\Support\PermitRules::requiresPermit($property->permit_type);

        return match (true) {
            $needsPermit && $property->permit_expires_at && $property->permit_expires_at->lt(today()) => Property::COMPLIANCE_EXPIRED,
            $this->missingItems($property) !== [] => Property::COMPLIANCE_DRAFT,
            \App\Support\PermitRules::needsApproval($property->permit_type) => Property::COMPLIANCE_PENDING,
            !$needsPermit, (bool) $property->permit_verified_at => Property::COMPLIANCE_APPROVED,
            default => Property::COMPLIANCE_PENDING,
        };
    }

    /**
     * Called after every create / update from the listing form: works out the permit state and keeps
     * `status` (website) off unless it's verified / approved. $before is null for a new listing.
     */
    public function afterSave(Property $property, ?array $before): void
    {
        $property->refresh();
        $changed = $before === null || $before !== $this->snapshot($property);
        $current = $property->compliance_status;
        $state = $this->stateFor($property);
        $awaitsApproval = $state === Property::COMPLIANCE_PENDING && \App\Support\PermitRules::needsApproval($property->permit_type);
        $adminApproves = $awaitsApproval && $property->portal_user_id === null && $this->actingAsAdmin();

        $next = match (true) {
            // Taken down by Super Admin: stays down until the permit details change.
            $current === Property::COMPLIANCE_CHANGES_REQUESTED && !$changed => $current,
            // An approval survives edits outside the permit's details (description, photos …).
            $awaitsApproval && $current === Property::COMPLIANCE_APPROVED && !$changed => $current,
            // Super Admin is the approver: an MW Realty (house) listing they save is approved.
            $adminApproves => Property::COMPLIANCE_APPROVED,
            default => $state,
        };

        if ($next !== $current || $before === null) {
            $note = match (true) {
                $adminApproves => 'Approved on save (MW Realty listing).',
                $next === Property::COMPLIANCE_APPROVED => \App\Support\PermitRules::requiresPermit($property->permit_type)
                    ? (in_array($property->permit_verified_via, ['dld', 'adrec'], true) ? 'Permit verified with ' . \App\Support\PermitRules::issuer($property->permit_type) . '.' : 'Permit verified.')
                    : 'No advertising permit needed.',
                $awaitsApproval => 'Waiting for MW Realty approval.',
                $next === Property::COMPLIANCE_PENDING => 'Permit not verified yet — validate it in Core details.',
                default => null,
            };
            $this->transition($property, $next, $note, [
                'compliance_submitted_at' => $next === Property::COMPLIANCE_PENDING ? now() : $property->compliance_submitted_at,
            ] + ($next === Property::COMPLIANCE_APPROVED ? ['compliance_reviewed_at' => now(), 'permit_expiry_notified_at' => null] : [])
              + ($adminApproves ? ['compliance_reviewed_by' => Auth::guard('cms')->id()] + $this->manualVerification($property) : []));

            if ($next === Property::COMPLIANCE_PENDING && $awaitsApproval && !$this->actingAsAdmin()) {
                $this->notifyAdmins($property, $before !== null && $current !== Property::COMPLIANCE_DRAFT);
                $this->notifyAccount($property, 'submitted');
            }
        }

        if ($property->status && !$property->canGoLive()) {
            $property->update(['status' => false]);
        }
    }

    /**
     * Super Admin approves a listing waiting on Listing Permits and publishes it. A permit nobody could
     * validate online (DTCM, or DLD / ADREC not connected) counts as checked by hand.
     */
    public function approve(Property $property): void
    {
        DB::transaction(function () use ($property) {
            $this->transition($property, Property::COMPLIANCE_APPROVED, 'Approved by MW Realty.', [
                'compliance_reviewed_at' => now(),
                'compliance_reviewed_by' => Auth::guard('cms')->id(),
                'permit_expiry_notified_at' => null,
            ] + $this->manualVerification($property));
            // Approval publishes the listing; the owner can still switch it off afterwards.
            $property->update(['status' => $property->canGoLive()]);
        });

        $this->notifyAccount($property, 'approved');
    }

    /** Permit columns recording a by-hand check, for a permit that isn't verified yet. */
    private function manualVerification(Property $property): array
    {
        return \App\Support\PermitRules::requiresPermit($property->permit_type) && !$property->permit_verified_at
            ? ['permit_verified_at' => now(), 'permit_verified_via' => 'manual']
            : [];
    }

    /** Super Admin takes a listing off the website (wrong details, a DLD complaint …) with a reason for the agent / agency. */
    public function takeDown(Property $property): void
    {
        DB::transaction(function () use ($property) {
            $this->transition($property, Property::COMPLIANCE_CHANGES_REQUESTED, 'Taken down by MW Realty.', [
                'compliance_reviewed_at' => now(),
                'compliance_reviewed_by' => Auth::guard('cms')->id(),
                'status' => false,
            ]);
        });

        $this->notifyAccount($property, 'changes_requested');
    }

    /** Scheduled: takes listings whose DLD permit has expired off the website. Returns how many. */
    public function expireDue(): int
    {
        $count = 0;
        Property::where('compliance_status', Property::COMPLIANCE_APPROVED)
            ->whereNotNull('permit_expires_at')
            ->whereDate('permit_expires_at', '<', today())
            ->chunkById(200, function ($properties) use (&$count) {
                foreach ($properties as $property) {
                    $this->transition($property, Property::COMPLIANCE_EXPIRED, 'DLD permit expired on ' . $property->permit_expires_at->format('d M Y') . '.', ['status' => false], 'system');
                    $this->notifyAccount($property, 'expired');
                    $count++;
                }
            });

        return $count;
    }

    /** Scheduled: one reminder per permit, EXPIRY_REMINDER_DAYS before it expires. Returns how many. */
    public function remindExpiring(): int
    {
        $count = 0;
        Property::where('compliance_status', Property::COMPLIANCE_APPROVED)
            ->whereNull('permit_expiry_notified_at')
            ->whereNotNull('permit_expires_at')
            ->whereDate('permit_expires_at', '>=', today())
            ->whereDate('permit_expires_at', '<=', today()->addDays(self::EXPIRY_REMINDER_DAYS))
            ->chunkById(200, function ($properties) use (&$count) {
                foreach ($properties as $property) {
                    $property->update(['permit_expiry_notified_at' => now()]);
                    $this->notifyAccount($property, 'expiring');
                    $count++;
                }
            });

        return $count;
    }

    private function transition(Property $property, string $to, ?string $note, array $extra = [], ?string $actorType = null): void
    {
        $from = $property->getOriginal('compliance_status');
        // The note only goes to the listing's history (there is no per-listing review note any more).
        $property->update(['compliance_status' => $to] + $extra);

        [$type, $id] = $actorType === 'system' ? ['system', null] : $this->actor();
        PropertyComplianceLog::create([
            'property_id' => $property->id,
            'from_status' => $from === $to ? null : $from,
            'to_status' => $to,
            'note' => $note,
            'actor_type' => $type,
            'actor_id' => $id,
        ]);
    }

    /**
     * Owning agency / agent and the assigned agent — each gets a bell notification and an email, once.
     * A failed notification or email is logged, never allowed to undo the review action itself.
     */
    private function notifyAccount(Property $property, string $event, ?string $note = null): void
    {
        PortalUser::whereIn('id', array_filter([$property->portal_user_id, $property->agent_id]))->get()
            ->each(function (PortalUser $user) use ($property, $event, $note) {
                $this->attempt('listing compliance bell', fn () => $user->notify(new ListingComplianceNotification($property, $event, $note)));
                if ($user->email) {
                    $this->attempt('listing compliance email', fn () => Mail::to($user->email)->queue((new ListingComplianceMail($user, $property, $event, $note))->afterCommit()));
                }
            });
    }

    /** Super Admin: bell for each superadmin, email to the site's notification address. */
    private function notifyAdmins(Property $property, bool $isResubmission): void
    {
        // whereHas, not Admin::role(): that throws when the role doesn't exist.
        $this->attempt('listing review bell', fn () => Admin::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->get()
            ->each(fn (Admin $admin) => $admin->notify(new ListingReviewRequestedNotification($property, $isResubmission))));

        if ($adminEmail = SiteInformation::notificationEmail()) {
            $this->attempt('listing review email', fn () => Mail::to($adminEmail)->queue((new ListingReviewRequestedMail($property, $isResubmission))->afterCommit()));
        }
    }

    private function actingAsAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    private function attempt(string $what, callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error("Failed to send {$what}: " . $e->getMessage());
        }
    }

    private function actor(): array
    {
        if ($portal = Auth::guard('portal')->user()) {
            return [$portal->isAgency() ? 'agency' : 'agent', $portal->id];
        }
        if ($admin = Auth::guard('cms')->id()) {
            return ['admin', $admin];
        }

        return ['system', null];
    }

    private function normalise($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
            return (string) (0 + $value);
        }

        return $value === null || $value === '' ? null : (string) $value;
    }
}
