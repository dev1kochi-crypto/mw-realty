{{--
    A website lead's tracked activity (App\Services\Visitors\VisitorInsights::for()) — the Insights
    tab of the CRM lead page and of Super Admin's website lead page. Built from the lead page's own
    pieces (portal-lp-card, portal-lp-stat, portal-lead-timeline) so it reads as part of that page.
    Expects: $lead (VisitorLead), $stats, $topProperties, $favorites, $savedSearches, $searchInterests,
    $conversations, $timeline, $timelineLimit.
--}}
@php
    $fmt = fn ($s) => \App\Services\Visitors\VisitorInsights::duration((int) $s);
    [$engagement, $engagementTone] = \App\Services\Visitors\VisitorInsights::engagement($stats);
    $maxPropertySeconds = max(1, (int) $topProperties->max('seconds'));
    $eventTones = [
        'property_view' => 'accent', 'favorite_added' => 'accent', 'enquiry' => 'warning', 'saved_search' => 'warning',
        'routed' => 'success', 'transferred' => 'success', 'identified' => 'success', 'chat_started' => 'primary', 'contact_click' => 'warning',
    ];
    $firstSeen = \Illuminate\Support\Carbon::parse($stats['first_seen']);
@endphp

@once
@push('styles')
<style>
    .vi-strip { padding: 1.1rem 1.25rem; margin-bottom: 1rem; }
    .vi-strip .portal-lp-stats { grid-template-columns: repeat(6, minmax(0, 1fr)); margin-top: 0; padding-top: 0; border-top: 0; }
    .vi-strip-foot { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.25rem; margin-top: 1rem; padding-top: .9rem; border-top: 1px dashed var(--portal-border, #e8eaf2); font-size: .8rem; color: var(--portal-muted, #6b7094); }
    .vi-strip-foot i { margin-right: .35rem; }
    .vi-engagement { display: inline-flex; align-items: center; gap: .35rem; font-weight: 700; font-size: .75rem; padding: .25rem .7rem; border-radius: 999px; }
    .vi-engagement.tone-accent { background: rgba(202, 40, 68, .1); color: var(--portal-accent, #ca2844); }
    .vi-engagement.tone-warning { background: #fff7e6; color: #b45309; }
    .vi-engagement.tone-muted { background: #f1f2f7; color: var(--portal-muted, #6b7094); }
    .vi-sub { font-size: .72rem; color: var(--portal-muted, #6b7094); font-weight: 400; }

    .vi-prop { display: flex; gap: .85rem; align-items: center; padding: .75rem 0; border-bottom: 1px solid #f1f2f7; }
    .vi-prop:last-child { border-bottom: 0; padding-bottom: 0; }
    .vi-prop:first-child { padding-top: 0; }
    .vi-prop-rank { width: 30px; height: 30px; flex: 0 0 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .8rem; background: #f1f2f7; color: #4a5080; }
    .vi-prop:first-child .vi-prop-rank { background: rgba(202, 40, 68, .1); color: var(--portal-accent, #ca2844); }
    .vi-prop-title { font-weight: 600; font-size: .88rem; color: #1f2340; text-decoration: none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vi-prop-title:hover { color: var(--portal-accent, #ca2844); }
    .vi-prop-meta { font-size: .75rem; color: var(--portal-muted, #6b7094); }
    .vi-bar { height: 5px; border-radius: 999px; background: #f1f2f7; overflow: hidden; margin-top: .4rem; }
    .vi-bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #f2a33a, #ca2844); }
    .vi-prop-nums { text-align: right; flex: 0 0 auto; font-size: .8rem; }
    .vi-prop-nums strong { display: block; font-size: .95rem; color: #1f2340; }

    .vi-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
    .vi-chip { display: inline-flex; align-items: center; gap: .35rem; font-size: .75rem; padding: .3rem .65rem; border-radius: 999px; background: #f4f5fa; color: #3b4064; text-decoration: none; }
    .vi-chip b { font-weight: 700; color: var(--portal-accent, #ca2844); }
    a.vi-chip:hover { background: rgba(202, 40, 68, .08); color: var(--portal-accent, #ca2844); }

    .vi-chat { border: 1px solid #eef0f6; border-radius: 12px; overflow: hidden; }
    .vi-chat + .vi-chat { margin-top: .6rem; }
    .vi-chat > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: .6rem; padding: .7rem .9rem; font-size: .82rem; background: #fafbfd; }
    .vi-chat > summary::-webkit-details-marker { display: none; }
    .vi-chat > summary .fa-chevron-down { margin-left: auto; color: #9aa0b8; transition: transform .2s; }
    .vi-chat[open] > summary .fa-chevron-down { transform: rotate(180deg); }
    .vi-chat-body { padding: .9rem; max-height: 420px; overflow-y: auto; display: flex; flex-direction: column; gap: .55rem; background: #fff; }
    .vi-msg { display: flex; gap: .5rem; align-items: flex-end; max-width: 88%; }
    .vi-msg.is-user { align-self: flex-end; flex-direction: row-reverse; }
    .vi-msg-avatar { width: 26px; height: 26px; flex: 0 0 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .68rem; color: #fff; background: linear-gradient(135deg, #ca2844, #244373); }
    .vi-msg.is-user .vi-msg-avatar { background: #e4e7f1; color: #4a5080; }
    .vi-msg-text { padding: .55rem .8rem; border-radius: 14px; font-size: .82rem; line-height: 1.45; white-space: pre-line; background: #f3f4f9; color: #1f2340; border-bottom-left-radius: 4px; }
    .vi-msg.is-user .vi-msg-text { background: var(--portal-accent, #ca2844); color: #fff; border-bottom-left-radius: 14px; border-bottom-right-radius: 4px; }
    .vi-msg-props { margin: -.15rem 0 0 34px; }

    .vi-chat-list { max-height: 520px; overflow-y: auto; padding-right: .25rem; }
    .vi-chat-list .vi-chat-body { max-height: 340px; }

    .vi-toggle { display: inline-flex; padding: 3px; border-radius: 999px; background: #f1f2f7; }
    .vi-toggle button { border: 0; background: none; font-size: .72rem; font-weight: 600; padding: .25rem .7rem; border-radius: 999px; color: var(--portal-muted, #6b7094); }
    .vi-toggle button.is-active { background: #fff; color: #1f2340; box-shadow: 0 1px 3px rgba(20, 24, 50, .12); }
    .vi-timeline-wrap { max-height: 380px; overflow-y: auto; padding-right: .35rem; }
    .vi-day { position: sticky; top: 0; z-index: 1; background: #fff; font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--portal-muted, #6b7094); padding: .55rem 0 .3rem; }
    .vi-act { display: flex; align-items: center; gap: .6rem; padding: .4rem 0; border-bottom: 1px solid #f4f5f9; font-size: .8rem; min-width: 0; }
    .vi-act:last-child { border-bottom: 0; }
    .vi-act-icon { width: 26px; height: 26px; flex: 0 0 26px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .68rem; background: #f1f2f7; color: var(--portal-muted, #6b7094); }
    .vi-act-icon.tone-accent { background: rgba(202, 40, 68, .1); color: var(--portal-accent, #ca2844); }
    .vi-act-icon.tone-warning { background: #fff7e6; color: #b45309; }
    .vi-act-icon.tone-success { background: #e8f7ef; color: #15803d; }
    .vi-act-icon.tone-primary { background: #e8eefb; color: #244373; }
    .vi-act-text { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vi-act-text strong { font-weight: 600; color: #1f2340; margin-right: .3rem; }
    .vi-act-text a { color: #244373; }
    .vi-act-time { flex: 0 0 auto; font-size: .72rem; color: var(--portal-muted, #6b7094); white-space: nowrap; }
    .vi-act-time i { margin-right: .2rem; }
    .vi-show-key .is-minor { display: none; }

    @media (max-width: 1199.98px) { .vi-strip .portal-lp-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .vi-strip .portal-lp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@push('scripts')
<script>
// Activity Timeline: "Key events" hides plain page views; "All activity" shows everything.
document.addEventListener('click', function (e) {
    const button = e.target.closest('[data-vi-show]');
    if (!button) return;
    const card = button.closest('[data-vi-activity]');
    card.querySelectorAll('[data-vi-show]').forEach((b) => b.classList.toggle('is-active', b === button));
    card.querySelector('.vi-timeline-wrap')?.classList.toggle('vi-show-key', button.dataset.viShow === 'key');
});
</script>
@endpush
@endonce

<div class="vi-panel">
    {{-- Engagement strip --}}
    <div class="portal-card vi-strip">
        <div class="portal-lp-stats">
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon"><i class="fas fa-clock"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Time on site</div><div class="portal-lp-stat-value">{{ $fmt($stats['site_seconds']) }}</div><div class="vi-sub">{{ $stats['page_views'] }} {{ \Illuminate\Support\Str::plural('page', $stats['page_views']) }} viewed</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-accent"><i class="fas fa-house"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Property views</div><div class="portal-lp-stat-value">{{ $stats['property_views'] }}</div><div class="vi-sub">{{ $stats['properties_viewed'] }} {{ \Illuminate\Support\Str::plural('listing', $stats['properties_viewed']) }} · {{ $fmt($stats['property_seconds']) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-warning"><i class="fas fa-magnifying-glass"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Searches</div><div class="portal-lp-stat-value">{{ $stats['searches'] }}</div><div class="vi-sub">{{ $stats['saved_searches'] }} saved</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-accent"><i class="fas fa-heart"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Favorites</div><div class="portal-lp-stat-value">{{ $stats['favorites'] }}</div><div class="vi-sub">{{ $lead->user_id ? 'customer account' : 'not signed in' }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon"><i class="fas fa-robot"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">AI chats</div><div class="portal-lp-stat-value">{{ $stats['chats'] }}</div><div class="vi-sub">{{ $conversations->sum('message_count') }} messages</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-success"><i class="fas fa-envelope-open-text"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Form enquiries</div><div class="portal-lp-stat-value">{{ $stats['enquiries'] }}</div><div class="vi-sub">website forms</div></div>
            </div>
        </div>
        <div class="vi-strip-foot">
            <span class="vi-engagement tone-{{ $engagementTone }}" title="From views, time on site, chats, enquiries and saves"><i class="fas fa-fire-flame-curved m-0"></i>{{ $engagement }} lead</span>
            <span><i class="far fa-calendar"></i>First seen {{ $firstSeen->format('d M Y, H:i') }}</span>
            <span><i class="fas fa-clock-rotate-left"></i>Last active {{ $stats['last_seen']?->diffForHumans() ?? '—' }}</span>
            <span><i class="fas fa-arrow-right-to-bracket"></i>Came in via {{ $lead->sourceLabel() }}</span>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            {{-- Most interested properties --}}
            <section class="portal-lp-card">
                <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-fire"></i></span>Most Interested Properties @if($topProperties->count() > 1)<span class="portal-lp-count">{{ $topProperties->count() }}</span>@endif</h2></header>
                <div class="portal-lp-card-body">
                    @forelse($topProperties as $row)
                    <div class="vi-prop">
                        <span class="vi-prop-rank">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-grow-1">
                            <a href="{{ url('/property-details/' . $row['property']->slug) }}" target="_blank" rel="noopener" class="vi-prop-title" title="{{ $row['property']->getTranslation('title') }}">{{ $row['property']->getTranslation('title') ?: $row['property']->reference_no }}</a>
                            <div class="vi-prop-meta">{{ $row['property']->owner?->displayName() ?? 'MW Realty' }} · last viewed {{ $row['last_viewed_at']->diffForHumans() }}</div>
                            <div class="vi-bar"><span style="width: {{ max(4, round(100 * $row['seconds'] / $maxPropertySeconds)) }}%;"></span></div>
                        </div>
                        <div class="vi-prop-nums"><strong>{{ $fmt($row['seconds']) }}</strong><span class="text-muted">{{ $row['views'] }} {{ \Illuminate\Support\Str::plural('view', $row['views']) }}</span></div>
                    </div>
                    @empty
                    <div class="portal-lp-empty">No property detail pages viewed yet.</div>
                    @endforelse
                </div>
            </section>

            {{-- Activity timeline — one compact row per event. Chats live in AI Chat History, so they're
                 left out here; plain page views only show under "All activity". --}}
            @php
                $activity = $timeline->reject(fn ($e) => $e->type === 'chat_started');
                $keyCount = $activity->where('type', '!=', 'page_view')->count();
            @endphp
            <section class="portal-lp-card" data-vi-activity>
                <header class="portal-lp-card-head">
                    <h2><span class="portal-lp-card-icon"><i class="fas fa-stream"></i></span>Activity Timeline <span class="portal-lp-count">{{ $keyCount }}</span></h2>
                    <div class="vi-toggle" role="group" aria-label="Show">
                        <button type="button" class="is-active" data-vi-show="key">Key events</button>
                        <button type="button" data-vi-show="all">All activity</button>
                    </div>
                </header>
                <div class="portal-lp-card-body">
                    @if($activity->isEmpty())
                    <div class="portal-lp-empty">No activity tracked yet.</div>
                    @else
                    <div class="vi-timeline-wrap vi-show-key">
                        @foreach($activity->groupBy(fn ($e) => $e->created_at->toDateString()) as $day => $events)
                        @php $date = \Illuminate\Support\Carbon::parse($day); @endphp
                        <div class="vi-day {{ $events->every(fn ($e) => $e->type === 'page_view') ? 'is-minor' : '' }}">{{ $date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format('D, d M Y')) }}</div>
                        @foreach($events as $event)
                        @php
                            $detail = match (true) {
                                in_array($event->type, ['routed', 'transferred'], true) => trim((!empty($event->meta['from']) ? $event->meta['from'] . ' → ' : '') . ($event->meta['owner'] ?? $event->title) . (!empty($event->meta['agent']) ? ' · agent ' . $event->meta['agent'] : '')),
                                in_array($event->type, ['search', 'saved_search'], true) => collect($event->meta['filters'] ?? [])->filter(fn ($v) => is_scalar($v) && $v !== '')->map(fn ($v, $k) => ucfirst(str_replace(['_', '-'], ' ', $k)) . ': ' . $v)->implode(' · ') ?: ($event->title ?: 'All listings'),
                                $event->type === 'enquiry' => trim(($event->title ?: 'Form') . (!empty($event->meta['message']) ? ' — “' . \Illuminate\Support\Str::limit($event->meta['message'], 80) . '”' : '')),
                                $event->type === 'page_view' => $event->title ?: $event->url,
                                default => $event->property ? null : $event->title,
                            };
                            $hint = $event->meta['reason'] ?? ($event->type === 'transferred' && !empty($event->meta['note']) ? 'Note: ' . $event->meta['note'] : null);
                        @endphp
                        <div class="vi-act {{ $event->type === 'page_view' ? 'is-minor' : '' }}" @if($hint) title="{{ $hint }}" @endif>
                            <span class="vi-act-icon tone-{{ $eventTones[$event->type] ?? 'muted' }}"><i class="fas {{ $event->icon() }}" aria-hidden="true"></i></span>
                            <span class="vi-act-text">
                                <strong>{{ $event->label() }}</strong>
                                @if($event->property && $event->type !== 'transferred')<a href="{{ url('/property-details/' . $event->property->slug) }}" target="_blank" rel="noopener">{{ $event->property->getTranslation('title') ?: $event->property->reference_no }}</a>@endif
                                @if($detail)<span class="text-muted">{{ $event->property && $event->type !== 'transferred' ? '→ ' : '' }}{{ $detail }}</span>@endif
                            </span>
                            <span class="vi-act-time">@if($event->duration_seconds > 0)<i class="far fa-clock"></i>{{ $fmt($event->duration_seconds) }} · @endif{{ $event->created_at->format('H:i') }}</span>
                        </div>
                        @endforeach
                        @endforeach
                        @if($timeline->count() >= $timelineLimit)
                        <div class="text-muted small mt-2">Showing the latest {{ $timelineLimit }} events.</div>
                        @endif
                    </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            {{-- AI chat history --}}
            <section class="portal-lp-card">
                <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-robot"></i></span>AI Chat History @if($conversations->count())<span class="portal-lp-count">{{ $conversations->count() }}</span>@endif</h2></header>
                <div class="portal-lp-card-body vi-chat-list">
                    @forelse($conversations as $conversation)
                    <details class="vi-chat" @if($loop->first) open @endif>
                        <summary>
                            <i class="fas fa-comments text-muted"></i>
                            <span><strong>{{ $conversation->created_at->format('d M Y, H:i') }}</strong> <span class="text-muted">· {{ $conversation->message_count }} messages</span></span>
                            <i class="fas fa-chevron-down"></i>
                        </summary>
                        <div class="vi-chat-body">
                            @foreach($conversation->messages as $message)
                            <div class="vi-msg {{ $message->role === 'user' ? 'is-user' : '' }}">
                                <span class="vi-msg-avatar">@if($message->role === 'user'){{ mb_strtoupper(mb_substr($lead->displayName(), 0, 1)) }}@else<i class="fas fa-robot"></i>@endif</span>
                                <div class="vi-msg-text">{{ $message->text }}</div>
                            </div>
                            @if(!empty($message->properties))
                            <div class="vi-chips vi-msg-props">
                                @foreach(array_slice($message->properties, 0, 6) as $card)
                                    @if(!empty($card['slug']))<a class="vi-chip" href="{{ url('/property-details/' . $card['slug']) }}" target="_blank" rel="noopener"><i class="fas fa-house"></i>{{ \Illuminate\Support\Str::limit($card['name'] ?? $card['slug'], 40) }}</a>@endif
                                @endforeach
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </details>
                    @empty
                    <div class="portal-lp-empty">No AI chat conversations.</div>
                    @endforelse
                </div>
            </section>

            {{-- What they search for --}}
            <section class="portal-lp-card">
                <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-warning"><i class="fas fa-magnifying-glass"></i></span>What They Search For</h2></header>
                <div class="portal-lp-card-body">
                    @if($searchInterests->isEmpty())
                    <div class="portal-lp-empty">No filtered searches yet.</div>
                    @else
                    <div class="vi-chips">
                        @foreach($searchInterests as $filter => $times)
                        <span class="vi-chip">{{ $filter }}@if($times > 1) <b>×{{ $times }}</b>@endif</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </section>

            {{-- Favorites & saved searches --}}
            <section class="portal-lp-card">
                <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-heart"></i></span>Favorites &amp; Saved Searches</h2></header>
                <div class="portal-lp-card-body">
                    @if($favorites->isEmpty() && $savedSearches->isEmpty())
                    <div class="portal-lp-empty">{{ $lead->user_id ? 'Nothing saved to their account yet.' : 'Needs a customer account — this visitor hasn\'t signed in.' }}</div>
                    @else
                    <div class="d-grid gap-2">
                        @foreach($favorites as $property)
                        <a href="{{ url('/property-details/' . $property->slug) }}" target="_blank" rel="noopener" class="portal-lp-contact text-decoration-none">
                            <span class="portal-lp-contact-icon is-accent"><i class="fas fa-heart" aria-hidden="true"></i></span>
                            <span class="portal-lp-contact-value text-truncate">{{ $property->getTranslation('title') ?: $property->reference_no }}</span>
                        </a>
                        @endforeach
                        @foreach($savedSearches as $search)
                        <div class="portal-lp-contact">
                            <span class="portal-lp-contact-icon"><i class="fas fa-bookmark" aria-hidden="true"></i></span>
                            <span class="portal-lp-contact-value text-truncate">{{ $search->title }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
