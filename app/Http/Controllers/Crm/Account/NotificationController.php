<?php

namespace App\Http\Controllers\Crm\Account;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * @group CRM Account
 *
 * The bell icon — the signed-in account's notifications (new leads, imports finished, KYC
 * approved…). For an agent / company their own; for a Super Admin (web app) the CMS admin's.
 */
class NotificationController extends Controller
{
    /**
     * Notifications
     *
     * Newest first, 20 per page. `route` = the CRM screen it opens (name + params), when it has
     * one; otherwise open `url`.
     *
     * @queryParam page integer Example: 1
     *
     * @response 200 {"data": [{"id": "9a1…", "title": "New lead", "message": "Sara Ahmed enquired about…", "icon": "fa-user-plus", "tone": "teal", "url": "https://…/portal/crm/leads/101", "route": {"name": "leads.show", "params": {"id": 101}}, "read": false, "created_at": "2026-10-09T10:00:00+00:00"}], "unread_count": 3, "next_page": null}
     */
    public function index(Request $request)
    {
        $page = $this->notifiable()->notifications()->latest()->paginate(20, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => collect($page->items())->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'icon' => $notification->data['icon'] ?? 'fa-bell',
                'tone' => $notification->data['tone'] ?? 'teal',
                'url' => $notification->data['url'] ?? null,
                'route' => $this->crmRoute($notification->data['url'] ?? null),
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
            'unread_count' => $this->notifiable()->unreadNotifications()->count(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /**
     * Mark one read
     *
     * @response 200 {"success": true}
     */
    public function markRead(string $id)
    {
        $this->notifiable()->notifications()->where('id', $id)->first()?->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all read
     *
     * @response 200 {"success": true}
     */
    public function markAllRead()
    {
        $this->notifiable()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function notifiable()
    {
        return Auth::guard('portal')->user() ?? Auth::guard('cms')->user();
    }

    /** A notification's (portal) link → the CRM screen that replaced it, for links stored before the move. */
    private function crmRoute(?string $url): ?array
    {
        $path = trim((string) parse_url((string) $url, PHP_URL_PATH), '/');
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        return match (true) {
            (bool) preg_match('#^(?:portal/crm|crm)/leads/(\d+)$#', $path, $m) => ['name' => 'leads.show', 'params' => ['id' => (int) $m[1]]],
            in_array($path, ['portal/crm/leads', 'crm/leads'], true) => ['name' => 'leads.index', 'query' => $query],
            in_array($path, ['portal/crm/lead-insights', 'crm/lead-insights'], true) => ['name' => 'lead-insights.index'],
            in_array($path, ['portal/dashboard', 'crm/dashboard'], true) => ['name' => 'dashboard'],
            default => null,
        };
    }
}
