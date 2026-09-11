<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Bell-icon dropdown backend for the Agent/Company self-service portal — reads/marks
 * database notifications on the "portal" guard's PortalUser (which uses Notifiable).
 */
class PortalNotificationController extends Controller
{
    public function markRead(string $id)
    {
        Auth::guard('portal')->user()->notifications()->where('id', $id)->first()?->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        Auth::guard('portal')->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
