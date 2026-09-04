<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'country',
        'page_url',
        'page_source',
        'message',
        'extra_fields',
    ];

    protected $casts = [
        'extra_fields' => 'array',
    ];
}


