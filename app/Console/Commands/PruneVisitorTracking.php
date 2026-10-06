<?php

namespace App\Console\Commands;

use App\Models\Visitors\VisitorBrowser;
use App\Models\Visitors\VisitorEvent;
use Illuminate\Console\Command;

/**
 * Daily clean-up for visitor tracking: activity of browsers that never identified themselves is
 * only kept so it can be attached to them if they do (VisitorTracker::identify) — after
 * ANONYMOUS_DAYS it's dropped, along with browsers nobody has used since. Identified website
 * leads keep their full history.
 */
class PruneVisitorTracking extends Command
{
    private const ANONYMOUS_DAYS = 30;

    protected $signature = 'visitors:prune';

    protected $description = 'Delete old anonymous visitor-tracking events and unused browsers';

    public function handle(): int
    {
        $cutoff = now()->subDays(self::ANONYMOUS_DAYS);

        $events = 0;
        do {
            $deleted = VisitorEvent::whereNull('visitor_lead_id')->where('created_at', '<', $cutoff)->limit(5000)->delete();
            $events += $deleted;
        } while ($deleted > 0);

        // AI chats of visitors who asked their free question but never shared their details.
        $chats = \App\Models\Visitors\ChatConversation::whereNull('visitor_lead_id')->where('updated_at', '<', $cutoff)->delete();

        $browsers = VisitorBrowser::whereNull('visitor_lead_id')->where('last_seen_at', '<', $cutoff)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('visitor_events')->whereColumn('visitor_events.visitor_browser_id', 'visitor_browsers.id'))
            ->delete();

        $this->info("Pruned {$events} anonymous events, {$chats} anonymous chats and {$browsers} unused browsers.");

        return self::SUCCESS;
    }
}
