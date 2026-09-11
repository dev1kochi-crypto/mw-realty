<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Bell-icon dropdown backend for Super Admin — reads/marks database notifications
 * on the "cms" guard's Admin model (App\Models\CmsKit\Admin, which uses Notifiable).
 */
class NotificationController extends Controller
{
    public function markRead(string $id)
    {
        Auth::guard('cms')->user()->notifications()->where('id', $id)->first()?->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        Auth::guard('cms')->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
