<?php

namespace App\Models\CmsKit;

use Illuminate\Database\Eloquent\Model;

class NewsletterSignup extends Model
{
    protected $fillable = ['email', 'is_subscribed', 'unsubscribe_token'];

    protected $casts = ['is_subscribed' => 'boolean'];
}

