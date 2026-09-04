<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class SiteInformation extends Model
{
    protected $table = 'site_information';

    protected $fillable = [
        'company_name', 'address', 'country', 'po_box', 'fax',
        'phone_1', 'phone_2', 'phone_3', 'phone_4', 'whatsapp_number',
        'email_1', 'email_2', 'email_3', 'email_4', 'receipt_email',
        'privacy_policy', 'terms_and_conditions', 'disclaimer',
        'logo', 'logo_alt', 'favicon', 'footer_logo', 'footer_logo_alt', 'footer_description',
        'facebook', 'twitter', 'linkedin', 'instagram', 'tiktok', 'snapchat',
        'pinterest', 'youtube', 'skype', 'whatsapp_social', 'vimeo',
        'gtag', 'custom_head_script', 'custom_body_script', 'extra_fields', 'translations'
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'translations' => 'array',
    ];
}


