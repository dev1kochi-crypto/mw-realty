<?php

namespace App\Services;

use App\Models\CmsKit\Admin;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyComplianceLog;
use App\Notifications\ListingComplianceNotification;
use App\Notifications\ListingReviewRequestedNotification;
use App\Mail\ListingComplianceMail;
use App\Mail\ListingReviewRequestedMail;
use App\Models\CmsKit\SiteInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Dubai listing compliance: a listing is only allowed on the website once it has
 *   1. a DLD advertising permit (Trakheesi permit number + expiry + Madmoun QR), which the
 *      brokerage obtains from DLD — the platform never issues permits, it records and checks them;
 *   2. the owner's marketing authorisation (RERA Form A) uploaded;
 *   3. Super Admin's review on the Listing Approvals page.
 *
 * The listing's `status` (website on/off) can only be true while compliance_status is APPROVED and
 * the permit hasn't expired (Property::canGoLive()). Changing permit / Form A details or the
 * advertised price, purpose, type, bedrooms or size sends an approved listing back for review, since
 * the DLD permit is issued for those exact details. Expired permits are taken off the site daily by
 * `properties:expire-permits`.
 *
 * When DLD grants Trakheesi API access, permit validation belongs here (before approve()) — the
 * rest of the application only reads compliance_status.
 */
class ListingComplianceService
{
    /** Fields the DLD permit / Form A cover — any change on an approved listing means a new review. */
    public const MATERIAL_FIELDS = [
        'permit_number', 'permit_expires_at', 'permit_qr', 'permit_verification_url',
        'authorization_type', 'authorization_expires_at', 'authorization_document',
        'title_deed_no', 'title_deed_document',
        'price', 'listing_type', 'property_type', 'bedrooms', 'sqft',
    ];

    /** Private compliance documents (served only through the portal, never public URLs). */
    public const DOCUMENT_FIELDS = [
        'authorization_document' => 'Form A (marketing agreement)',
        'title_deed_document' => 'Title deed / Oqood',
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

    /** Saves the permit QR (public — shown on the website) and the private Form A / title deed files. */
    public function storeUploads(Request $request, Property $property): void
    {
        $updates = [];
        if ($request->hasFile('permit_qr')) {
            $this->files->delete($property->permit_qr);
            $updates['permit_qr'] = $this->files->store($request->file('permit_qr'), 'properties/permits');
        }
        foreach (array_keys(self::DOCUMENT_FIELDS) as $field) {
            if ($request->hasFile($field)) {
                $this->files->delete($property->{$field}, 'kyc');
                $updates[$field] = $this->files->store($request->file($field), 'property-compliance/' . $property->id, 'kyc');
            }
        }
        if ($updates) {
            $property->update($updates);
        }
    }

    /** What still has to be provided before the listing can be reviewed (empty = ready). */
    public function missingItems(Property $property): array
    {
        $missing = [];
        if (!$property->permit_number) {
            $missing[] = 'DLD advertising permit number (Trakheesi)';
        }
        if (!$property->permit_expires_at) {
            $missing[] = 'Permit expiry date';
        } elseif ($property->permit_expires_at->lt(today())) {
            $missing[] = 'A valid permit — the recorded one has expired';
        }
        if (!$property->permit_qr) {
            $missing[] = 'Permit QR code (Madmoun)';
        }
        if (!$property->authorization_type) {
            $missing[] = 'Marketing agreement type (exclusive / non-exclusive)';
        }
        if (!$property->authorization_document) {
            $missing[] = 'Form A (owner\'s marketing agreement)';
        } elseif ($property->authorization_expires_at && $property->authorization_expires_at->lt(today())) {
            $missing[] = 'A valid Form A — the recorded one has expired';
        }

        return $missing;
    }

    /**
     * Called after every create / update from the listing form. Moves the review state on and keeps
     * `status` off while the listing isn't approved. $before is null for a new listing.
     */
    public function afterSave(Property $property, ?array $before): void
    {
        $property->refresh();
        $complete = $this->missingItems($property) === [];
        $changed = $before === null || $before !== $this->snapshot($property);
        $current = $property->compliance_status;
        $houseListing = $property->portal_user_id === null;

        if ($this->actingAsAdmin()) {
            // Super Admin is the reviewer: an MW Realty (house) listing is approved as soon as its
            // permit details are complete. Agency / agent listings are approved explicitly from
            // Listing Approvals, so an admin edit only moves a finished draft into the queue.
            $next = match (true) {
                $houseListing && $complete => Property::COMPLIANCE_APPROVED,
                $houseListing => Property::COMPLIANCE_DRAFT,
                $current === Property::COMPLIANCE_DRAFT && $complete => Property::COMPLIANCE_PENDING,
                default => $current,
            };
        } else {
            $next = match (true) {
                // Edits outside the permit's details (description, photos…) keep an approval.
                $current === Property::COMPLIANCE_APPROVED && !$changed => $current,
                !$complete => in_array($current, [Property::COMPLIANCE_CHANGES_REQUESTED, Property::COMPLIANCE_EXPIRED], true) && !$changed
                    ? $current : Property::COMPLIANCE_DRAFT,
                $current === Property::COMPLIANCE_PENDING => $current,
                default => Property::COMPLIANCE_PENDING,
            };
        }

        if ($next !== $current || $before === null) {
            $this->transition($property, $next, $next === Property::COMPLIANCE_APPROVED && $this->actingAsAdmin() ? 'Approved on save (MW Realty listing).' : null, [
                'compliance_submitted_at' => $next === Property::COMPLIANCE_PENDING ? now() : $property->compliance_submitted_at,
            ] + ($next === Property::COMPLIANCE_APPROVED ? ['compliance_reviewed_at' => now(), 'compliance_reviewed_by' => Auth::guard('cms')->id()] : []));

            if ($next === Property::COMPLIANCE_PENDING) {
                $this->notifyAdmins($property, $current !== Property::COMPLIANCE_DRAFT);
                // A Super Admin moving a finished draft into the queue isn't the account submitting it.
                if (!$this->actingAsAdmin()) {
                    $this->notifyAccount($property, 'submitted');
                }
            }
        }

        if ($property->status && !$property->canGoLive()) {
            $property->update(['status' => false]);
        }
    }

    public function approve(Property $property, ?string $note = null): void
    {
        DB::transaction(function () use ($property, $note) {
            $this->transition($property, Property::COMPLIANCE_APPROVED, $note, [
                'compliance_reviewed_at' => now(),
                'compliance_reviewed_by' => Auth::guard('cms')->id(),
                'permit_expiry_notified_at' => null,
            ]);
            // Approval publishes the listing; the owner can still switch it off afterwards.
            $property->update(['status' => $property->canGoLive()]);
        });

        $this->notifyAccount($property, 'approved', $note);
    }

    /** Sends the listing back to the agent / agency with a reason. Also used to take a live listing down. */
    public function requestChanges(Property $property, string $note): void
    {
        DB::transaction(function () use ($property, $note) {
            $this->transition($property, Property::COMPLIANCE_CHANGES_REQUESTED, $note, [
                'compliance_reviewed_at' => now(),
                'compliance_reviewed_by' => Auth::guard('cms')->id(),
                'status' => false,
            ]);
        });

        $this->notifyAccount($property, 'changes_requested', $note);
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
        $property->update(['compliance_status' => $to, 'compliance_note' => $note] + $extra);

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

    private function attempt(string $what, callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error("Failed to send {$what}: " . $e->getMessage());
        }
    }

    private function actingAsAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
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
