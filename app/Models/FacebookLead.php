<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A Facebook Lead Ads lead received (by leadgen id) and the CRM lead it became. */
class FacebookLead extends Model
{
    protected $fillable = ['facebook_page_connection_id', 'leadgen_id', 'page_id', 'form_id', 'ad_id', 'ad_name', 'lead_id', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function connection()
    {
        return $this->belongsTo(FacebookPageConnection::class, 'facebook_page_connection_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
