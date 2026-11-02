<?php

return [
    /*
    | Listing Review (App\Services\ListingReview\ListingReviewService): how many minutes a person would
    | spend checking one listing by hand (permit, duplicates, photos, price, advertiser). Used for the
    | "time saved" measure: every listing screened automatically that didn't need a human saves this.
    */
    'minutes_per_check' => (int) env('LISTING_REVIEW_MINUTES_PER_CHECK', 10),
];
