<?php

namespace App\Models\CmsKit;

use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $fillable = [
        'property_id',
        'portal_user_id',
        'name',
        'email',
        'phone',
        'company',
        'country',
        'page_url',
        'page_source',
        'message',
        'status',
        'notes',
        'extra_fields',
    ];

    protected $casts = [
        'extra_fields' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function owner()
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }
}


