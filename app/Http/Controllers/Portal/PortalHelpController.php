<?php

namespace App\Http\Controllers\Portal;

use App\Support\PortalHelp;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Remembers which module guides a portal user has already been shown, so each one opens
 * by itself only once (afterwards it's reopened from the top bar's "!" icon).
 */
class PortalHelpController extends Controller
{
    public function seen(string $topic)
    {
        abort_unless(PortalHelp::exists($topic), 404);

        $user = Auth::guard('portal')->user();
        $seen = $user->seen_help_topics ?? [];
        if (! in_array($topic, $seen, true)) {
            $user->forceFill(['seen_help_topics' => [...$seen, $topic]])->save();
        }

        return response()->json(['success' => true]);
    }
}
