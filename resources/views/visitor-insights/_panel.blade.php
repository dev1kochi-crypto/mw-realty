{{--
    A website lead's tracked activity (App\Services\Visitors\VisitorInsights::for()) — the Insights
    tab of the CRM lead page and of Super Admin's website lead page. Built from the lead page's own
    pieces (portal-lp-card, portal-lp-stat, portal-lead-timeline) so it reads as part of that page.
    Expects: $lead (VisitorLead), $stats, $topProperties, $searchInterests, $activityCounts, $feedUrl,
    and the first page (['items', 'next']) of $timeline, $favorites, $savedSearches, $conversations.
    Long lists (a lead can have thousands of events) load 20 rows at a time as they scroll: each
    [data-vi-feed] list asks $feedUrl for the rows after its data-vi-next cursor (VisitorInsights::feed()).
--}}
@php
    $fmt = fn ($s) => \App\Services\Visitors\VisitorInsights::duration((int) $s);
    [$engagement, $engagementTone] = \App\Services\Visitors\VisitorInsights::engagement($stats);
    $maxPropertySeconds = max(1, (int) $topProperties->max('seconds'));
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
    .vi-feed-more { text-align: center; font-size: .75rem; color: var(--portal-muted, #6b7094); padding: .6rem 0; }
    .vi-tab-count { display: inline-block; min-width: 1.2rem; margin-left: .2rem; padding: 0 .3rem; border-radius: 999px; background: rgba(31, 35, 64, .07); font-size: .66rem; text-align: center; }
    .vi-toggle button.is-active .vi-tab-count { background: rgba(202, 40, 68, .1); color: var(--portal-accent, #ca2844); }

    .vi-saved-list { max-height: 340px; overflow-y: auto; margin: -.25rem -.35rem; padding: .25rem .35rem; }
    .vi-saved { display: flex; align-items: center; gap: .7rem; padding: .5rem .4rem; border-radius: 10px; text-decoration: none; color: inherit; }
    .vi-saved + .vi-saved { border-top: 1px solid #f4f5f9; }
    .vi-saved:hover { background: #fafbfd; }
    .vi-saved:hover .vi-saved-title { color: var(--portal-accent, #ca2844); }
    .vi-saved-thumb { width: 40px; height: 40px; flex: 0 0 40px; border-radius: 9px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: rgba(202, 40, 68, .08); color: var(--portal-accent, #ca2844); font-size: .8rem; }
    .vi-saved-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .vi-saved-thumb.is-search { background: #e8eefb; color: #244373; }
    .vi-saved-title { display: block; font-weight: 600; font-size: .84rem; color: #1f2340; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vi-saved-meta { display: block; font-size: .72rem; color: var(--portal-muted, #6b7094); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vi-saved-time { flex: 0 0 auto; font-size: .7rem; color: var(--portal-muted, #6b7094); white-space: nowrap; }

    @media (max-width: 1199.98px) { .vi-strip .portal-lp-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .vi-strip .portal-lp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // Load on scroll: a [data-vi-feed] list is its own scroll box ending in a .vi-feed-more sentinel;
    // when the sentinel scrolls into view the next 20 rows after data-vi-next are fetched and
    // appended. No data-vi-next = everything is loaded. Hidden lists (closed chats, the other tab,
    // the Insights tab itself) don't load until they're shown.
    const observers = new WeakMap();

    function sentinelOf(feed) {
        let sentinel = feed.querySelector(':scope > .vi-feed-more');
        if (!sentinel) {
            sentinel = document.createElement('div');
            sentinel.className = 'vi-feed-more';
            sentinel.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            feed.append(sentinel);
        }
        return sentinel;
    }

    function watch(root) {
        root.querySelectorAll('[data-vi-feed]').forEach((feed) => {
            if (observers.has(feed) || !('viNext' in feed.dataset)) return;
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) load(feed);
            }, { root: feed, rootMargin: '0px 0px 150px 0px' });
            observers.set(feed, observer);
            observer.observe(sentinelOf(feed));
        });
    }

    function load(feed) {
        if (feed.dataset.viLoading || !('viNext' in feed.dataset)) return;
        feed.dataset.viLoading = '1';
        const sentinel = sentinelOf(feed);
        sentinel.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        const url = new URL(feed.closest('[data-vi-url]').dataset.viUrl, window.location.origin);
        const params = Object.assign({ section: feed.dataset.viFeed, after: feed.dataset.viNext }, JSON.parse(feed.dataset.viParams || '{}'));
        Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
        const filter = params.filter;

        fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((page) => {
                // The timeline switched Key events ↔ All activity while this page was on its way.
                if (filter !== JSON.parse(feed.dataset.viParams || '{}').filter) return;
                const chunk = document.createElement('template');
                chunk.innerHTML = page.html;
                // A page that starts on the same day the previous one ended on doesn't repeat the day header.
                const firstDay = chunk.content.querySelector('[data-vi-day]');
                const days = feed.querySelectorAll(':scope > [data-vi-day]');
                if (firstDay && days.length && days[days.length - 1].dataset.viDay === firstDay.dataset.viDay) firstDay.remove();
                feed.insertBefore(chunk.content, sentinel);

                if (page.next) {
                    feed.dataset.viNext = page.next;
                } else {
                    delete feed.dataset.viNext;
                    sentinel.remove();
                    if (!feed.children.length && feed.dataset.viEmpty) {
                        feed.innerHTML = '<div class="portal-lp-empty"></div>';
                        feed.firstChild.textContent = feed.dataset.viEmpty;
                    }
                }
                watch(feed);
                // Still in view (a short page)? Re-observing makes the observer report again.
                if (page.next) {
                    observers.get(feed)?.unobserve(sentinel);
                    observers.get(feed)?.observe(sentinel);
                }
            })
            .catch(() => {
                sentinel.innerHTML = 'Couldn\'t load more. <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-vi-retry>Retry</button>';
            })
            .finally(() => { delete feed.dataset.viLoading; });
    }

    document.addEventListener('click', function (e) {
        const retry = e.target.closest('[data-vi-retry]');
        if (retry) {
            load(retry.closest('[data-vi-feed]'));
            return;
        }

        // Activity Timeline: "Key events" leaves out plain page views; "All activity" shows everything.
        // Filtered on the server, so the list restarts from the newest event.
        const show = e.target.closest('[data-vi-show]');
        if (show && !show.classList.contains('is-active')) {
            const card = show.closest('[data-vi-activity]');
            card.querySelectorAll('[data-vi-show]').forEach((b) => b.classList.toggle('is-active', b === show));
            card.querySelector('[data-vi-activity-count]').textContent = show.dataset.count;
            const feed = card.querySelector('[data-vi-feed]');
            if (!feed) return;
            feed.dataset.viParams = JSON.stringify({ filter: show.dataset.viShow });
            feed.dataset.viNext = '0';
            delete feed.dataset.viLoading;
            feed.replaceChildren();
            feed.scrollTop = 0;
            observers.get(feed)?.disconnect();
            observers.delete(feed);
            watch(card);
            return;
        }

        // Saved by Lead: switch between the Favorites and Saved searches lists.
        const tab = e.target.closest('[data-vi-tab]');
        if (tab) {
            const card = tab.closest('[data-vi-tabs]');
            card.querySelectorAll('[data-vi-tab]').forEach((b) => b.classList.toggle('is-active', b === tab));
            card.querySelectorAll('[data-vi-pane]').forEach((pane) => { pane.hidden = pane.dataset.viPane !== tab.dataset.viTab; });
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => watch(document));
    } else {
        watch(document);
    }
})();
</script>
@endpush
@endonce

<div class="vi-panel" data-vi-url="{{ $feedUrl }}">
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
                <div class="min-w-0"><div class="portal-lp-stat-label">AI chats</div><div class="portal-lp-stat-value">{{ $stats['chats'] }}</div><div class="vi-sub">{{ number_format($stats['chat_messages']) }} messages</div></div>
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

            {{-- Activity timeline — one compact row per event, newest first, 20 more on each scroll. Chats
                 live in AI Chat History, so they're left out; plain page views only under "All activity". --}}
            <section class="portal-lp-card" data-vi-activity>
                <header class="portal-lp-card-head">
                    <h2><span class="portal-lp-card-icon"><i class="fas fa-stream"></i></span>Activity Timeline <span class="portal-lp-count" data-vi-activity-count>{{ number_format($activityCounts['key']) }}</span></h2>
                    @if($activityCounts['all'])
                    <div class="vi-toggle" role="group" aria-label="Show">
                        <button type="button" class="is-active" data-vi-show="key" data-count="{{ number_format($activityCounts['key']) }}">Key events</button>
                        <button type="button" data-vi-show="all" data-count="{{ number_format($activityCounts['all']) }}">All activity</button>
                    </div>
                    @endif
                </header>
                <div class="portal-lp-card-body">
                    @if(!$activityCounts['all'])
                    <div class="portal-lp-empty">No activity tracked yet.</div>
                    @else
                    <div class="vi-timeline-wrap" data-vi-feed="timeline" data-vi-params='{"filter":"key"}' data-vi-empty="No key events yet — switch to All activity to see page views."
                         @if($timeline['next']) data-vi-next="{{ $timeline['next'] }}" @endif>
                        @if($timeline['items']->isEmpty())
                        <div class="portal-lp-empty">No key events yet — switch to All activity to see page views.</div>
                        @else
                        @include('visitor-insights._rows', ['section' => 'timeline', 'items' => $timeline['items']])
                        @endif
                    </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            {{-- AI chat history --}}
            <section class="portal-lp-card">
                <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-robot"></i></span>AI Chat History @if($stats['chats'])<span class="portal-lp-count">{{ number_format($stats['chats']) }}</span>@endif</h2></header>
                <div class="portal-lp-card-body vi-chat-list" data-vi-feed="chats" @if($conversations['next']) data-vi-next="{{ $conversations['next'] }}" @endif>
                    @if($conversations['items']->isEmpty())
                    <div class="portal-lp-empty">No AI chat conversations.</div>
                    @else
                    @include('visitor-insights._rows', ['section' => 'chats', 'items' => $conversations['items']])
                    @endif
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

            {{-- Favorites & saved searches — one tab each, compact rows, 20 more on each scroll. --}}
            @php $savedTab = $favorites['items']->isEmpty() && $savedSearches['items']->isNotEmpty() ? 'searches' : 'favorites'; @endphp
            <section class="portal-lp-card" data-vi-tabs>
                <header class="portal-lp-card-head">
                    <h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-heart"></i></span>Saved by Lead</h2>
                    @if($favorites['items']->isNotEmpty() || $savedSearches['items']->isNotEmpty())
                    <div class="vi-toggle" role="tablist">
                        <button type="button" class="{{ $savedTab === 'favorites' ? 'is-active' : '' }}" data-vi-tab="favorites"><i class="fas fa-heart me-1"></i>Favorites <span class="vi-tab-count">{{ number_format($stats['favorites']) }}</span></button>
                        <button type="button" class="{{ $savedTab === 'searches' ? 'is-active' : '' }}" data-vi-tab="searches"><i class="fas fa-bookmark me-1"></i>Searches <span class="vi-tab-count">{{ number_format($stats['saved_searches']) }}</span></button>
                    </div>
                    @endif
                </header>
                <div class="portal-lp-card-body">
                    @if($favorites['items']->isEmpty() && $savedSearches['items']->isEmpty())
                    <div class="portal-lp-empty">{{ $lead->user_id ? 'Nothing saved to their account yet.' : 'Needs a customer account — this visitor hasn\'t signed in.' }}</div>
                    @else
                    @foreach(['favorites' => [$favorites, 'No favorite properties.'], 'searches' => [$savedSearches, 'No saved searches.']] as $pane => [$page, $empty])
                    <div class="vi-saved-list" data-vi-pane="{{ $pane }}" data-vi-feed="{{ $pane }}" @if($page['next']) data-vi-next="{{ $page['next'] }}" @endif @if($savedTab !== $pane) hidden @endif>
                        @if($page['items']->isEmpty())
                        <div class="portal-lp-empty">{{ $empty }}</div>
                        @else
                        @include('visitor-insights._rows', ['section' => $pane, 'items' => $page['items']])
                        @endif
                    </div>
                    @endforeach
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
