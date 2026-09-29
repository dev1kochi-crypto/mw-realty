<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One listing on Super Admin's Marketing Properties list — see MarketingPropertyService. */
class MarketingProperty extends Model
{
    protected $fillable = ['property_id', 'order_index'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
