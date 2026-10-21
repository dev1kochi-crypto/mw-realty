<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Facebook user's data deletion request (Api\FacebookDataDeletionController). Its confirmation code
 * is what Facebook shows the user, and opens the status page.
 */
class FacebookDataDeletionRequest extends Model
{
    protected $fillable = ['confirmation_code', 'facebook_user_id', 'pages_removed', 'completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
