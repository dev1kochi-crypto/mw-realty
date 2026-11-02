<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** dHash fingerprint of one gallery photo (App\Services\ListingReview\ImageHasher). */
class PropertyImageHash extends Model
{
    protected $fillable = ['property_id', 'number', 'hash'];

    protected $casts = ['hash' => 'integer'];
}
