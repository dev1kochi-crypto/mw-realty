<?php

namespace App\Services\PropertyFinder;

use App\Models\CmsKit\Admin;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyFinderImport;
use App\Notifications\PropertyFinderNotification;
use App\Services\ListingComplianceService;
use App\Services\PropertyGallery;
use App\Support\PermitRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Super Admin's review of imported Property Finder listings.
 *
 *   approve → the listing joins the normal permit flow (ListingComplianceService): with its permit
 *             details complete it is verified / approved and goes live; otherwise it stays a draft
 *             off the website until the account completes the permit in the property form.
 *   reject  → the property (and its photos) is removed; the listing is never imported again.
 */
class PropertyFinderReview
{
    public function __construct(private readonly ListingComplianceService $compliance, private readonly PropertyGallery $gallery)
    {
    }

    /** @param  \Illuminate\Support\Collection<PropertyFinderImport>  $imports */
    public function approve($imports): int
    {
        $done = 0;
        foreach ($imports as $import) {
            if ($import->review_status !== PropertyFinderImport::PENDING) {
                continue;
            }
            DB::transaction(function () use ($import) {
                $import->update(['review_status' => PropertyFinderImport::APPROVED, 'reviewed_at' => now(), 'reviewed_by' => Auth::guard('cms')->id()]);
                if ($property = $import->property) {
                    $property->update(['metadata' => array_diff_key((array) $property->metadata, ['property_finder_review' => true])]);
                    $this->compliance->afterSave($property, null);
                    $property->refresh();
                    // It has its permit number — only verification is left (Validate fills in the expiry):
                    // "Not verified", not "Permit details needed".
                    if ($property->compliance_status === Property::COMPLIANCE_DRAFT && $property->permit_number && $property->emirate) {
                        $property->update(['compliance_status' => Property::COMPLIANCE_PENDING, 'compliance_submitted_at' => now()]);
                    }
                    // This review is the Super Admin approval — no second round on Listing Permits.
                    if ($property->compliance_status === Property::COMPLIANCE_PENDING && PermitRules::needsApproval($property->permit_type)) {
                        $this->compliance->approve($property);
                    } elseif ($property->canGoLive() && !$property->status) {
                        $property->update(['status' => true]);
                    }
                }
            });
            $done++;
        }
        $this->notifyOwners($imports->where('review_status', PropertyFinderImport::APPROVED), 'approved');

        return $done;
    }

    /** @param  \Illuminate\Support\Collection<PropertyFinderImport>  $imports */
    public function reject($imports, ?string $note = null): int
    {
        $done = 0;
        foreach ($imports as $import) {
            if ($import->review_status !== PropertyFinderImport::PENDING) {
                continue;
            }
            if ($property = $import->property) {
                $this->gallery->deleteAll($property->image_path);
                $property->details()->delete();
                $property->delete();
            }
            $import->update([
                'review_status' => PropertyFinderImport::REJECTED, 'property_id' => null,
                'reviewed_at' => now(), 'reviewed_by' => Auth::guard('cms')->id(), 'review_note' => $note,
            ]);
            $done++;
        }
        $this->notifyOwners($imports->where('review_status', PropertyFinderImport::REJECTED), 'rejected', $note);

        return $done;
    }

    /** After a sync added listings: Super Admin's bell, so they're reviewed. */
    public static function notifyAdmins(PortalUser $owner, int $added): void
    {
        try {
            Admin::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->get()
                ->each(fn (Admin $admin) => $admin->notify(new PropertyFinderNotification('review', $owner, $added)));
        } catch (\Throwable $e) {
            Log::error('Property Finder review bell failed: ' . $e->getMessage());
        }
    }

    private function notifyOwners($imports, string $event, ?string $note = null): void
    {
        foreach ($imports->groupBy('portal_user_id') as $ownerId => $group) {
            try {
                PortalUser::find($ownerId)?->notify(new PropertyFinderNotification($event, null, $group->count(), $note));
            } catch (\Throwable $e) {
                Log::error('Property Finder review notification failed: ' . $e->getMessage());
            }
        }
    }
}
