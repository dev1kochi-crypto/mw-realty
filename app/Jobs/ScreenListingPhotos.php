<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\ListingReview\ImageHasher;
use App\Services\ListingReview\ListingReviewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fingerprints a listing's photos (downloading them can take a while, so off the request) and then
 * screens it again — another agency's listing with the same photos is a likely duplicate.
 */
class ScreenListingPhotos implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public int $propertyId) {}

    public function handle(ImageHasher $hasher, ListingReviewService $reviews): void
    {
        $property = Property::find($this->propertyId);
        if ($property && $hasher->hash($property)) {
            // Not counted again for "time saved" — the save that queued this already was.
            $reviews->screen($property->refresh(), record: false, photos: false);
        }
    }
}
