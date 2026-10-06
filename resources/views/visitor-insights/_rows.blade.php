{{--
    One page of rows for an Insights panel list (visitor-insights._panel) — rendered on the first
    page load and again for every load-on-scroll request (VisitorInsights::feed()).
    Expects: $section (timeline | favorites | searches | chats | messages), $items, $lead (VisitorLead).
--}}
@php
    $fmt = fn ($s) => \App\Services\Visitors\VisitorInsights::duration((int) $s);
    $filterLabels = fn ($filters) => collect($filters ?? [])
        ->filter(fn ($v, $k) => is_scalar($v) && $v !== '' && !in_array($k, ['page', 'sort', 'lang', 'view'], true))
        ->map(fn ($v, $k) => ucfirst(str_replace(['_', '-'], ' ', $k)) . ': ' . $v);
@endphp
@switch($section)
@case('timeline')
    @php
        $eventTones = [
            'property_view' => 'accent', 'favorite_added' => 'accent', 'enquiry' => 'warning', 'saved_search' => 'warning',
            'routed' => 'success', 'transferred' => 'success', 'identified' => 'success', 'chat_started' => 'primary', 'contact_click' => 'warning',
        ];
        $previousDay = null;
    @endphp
    @foreach($items as $event)
    @php $day = $event->created_at->toDateString(); @endphp
    @if($day !== $previousDay)
    {{-- The panel's script drops this header when the previous page already ended on the same day. --}}
    <div class="vi-day" data-vi-day="{{ $day }}">{{ $event->created_at->isToday() ? 'Today' : ($event->created_at->isYesterday() ? 'Yesterday' : $event->created_at->format('D, d M Y')) }}</div>
    @php $previousDay = $day; @endphp
    @endif
    @php
        $detail = match (true) {
            in_array($event->type, ['routed', 'transferred'], true) => trim((!empty($event->meta['from']) ? $event->meta['from'] . ' → ' : '') . ($event->meta['owner'] ?? $event->title) . (!empty($event->meta['agent']) ? ' · agent ' . $event->meta['agent'] : '')),
            in_array($event->type, ['search', 'saved_search'], true) => $filterLabels($event->meta['filters'] ?? [])->implode(' · ') ?: ($event->title ?: 'All listings'),
            $event->type === 'enquiry' => trim(($event->title ?: 'Form') . (!empty($event->meta['message']) ? ' — “' . \Illuminate\Support\Str::limit($event->meta['message'], 80) . '”' : '')),
            $event->type === 'page_view' => $event->title ?: $event->url,
            default => $event->property ? null : $event->title,
        };
        $hint = $event->meta['reason'] ?? ($event->type === 'transferred' && !empty($event->meta['note']) ? 'Note: ' . $event->meta['note'] : null);
    @endphp
    <div class="vi-act" @if($hint) title="{{ $hint }}" @endif>
        <span class="vi-act-icon tone-{{ $eventTones[$event->type] ?? 'muted' }}"><i class="fas {{ $event->icon() }}" aria-hidden="true"></i></span>
        <span class="vi-act-text">
            <strong>{{ $event->label() }}</strong>
            @if($event->property && $event->type !== 'transferred')<a href="{{ url('/property-details/' . $event->property->slug) }}" target="_blank" rel="noopener">{{ $event->property->getTranslation('title') ?: $event->property->reference_no }}</a>@endif
            @if($detail)<span class="text-muted">{{ $event->property && $event->type !== 'transferred' ? '→ ' : '' }}{{ $detail }}</span>@endif
        </span>
        <span class="vi-act-time">@if($event->duration_seconds > 0)<i class="far fa-clock"></i>{{ $fmt($event->duration_seconds) }} · @endif{{ $event->created_at->format('H:i') }}</span>
    </div>
    @endforeach
    @break

@case('favorites')
    @foreach($items as $property)
    @php $thumb = $property->galleryImages()[0]['url'] ?? null; @endphp
    <a href="{{ url('/property-details/' . $property->slug) }}" target="_blank" rel="noopener" class="vi-saved">
        <span class="vi-saved-thumb">@if($thumb)<img src="{{ $thumb }}" alt="" loading="lazy">@else<i class="fas fa-house"></i>@endif</span>
        <span class="min-w-0 flex-grow-1">
            <span class="vi-saved-title">{{ $property->getTranslation('title') ?: $property->reference_no }}</span>
            <span class="vi-saved-meta">{{ collect([$property->bedrooms ? $property->bedrooms . ' BR' : null, $property->price ? $property->currency . ' ' . number_format($property->price) : null, $property->reference_no])->filter()->implode(' · ') }}</span>
        </span>
        <span class="vi-saved-time">{{ $property->pivot?->created_at?->diffForHumans(short: true) }}</span>
    </a>
    @endforeach
    @break

@case('searches')
    @foreach($items as $search)
    @php $criteria = $filterLabels($search->criteria); @endphp
    <a href="{{ url('/properties') . ($search->criteria ? '?' . http_build_query($search->criteria) : '') }}" target="_blank" rel="noopener" class="vi-saved" title="Open these results on the website">
        <span class="vi-saved-thumb is-search"><i class="fas fa-bookmark"></i></span>
        <span class="min-w-0 flex-grow-1">
            <span class="vi-saved-title">{{ $search->title }}</span>
            <span class="vi-saved-meta">{{ $criteria->implode(' · ') ?: 'All listings' }}</span>
        </span>
        <span class="vi-saved-time">{{ $search->created_at?->diffForHumans(short: true) }}</span>
    </a>
    @endforeach
    @break

@case('chats')
    {{-- Messages load when a conversation is opened, 20 at a time as it scrolls. --}}
    @foreach($items as $conversation)
    <details class="vi-chat">
        <summary>
            <i class="fas fa-comments text-muted"></i>
            <span><strong>{{ ($conversation->last_message_at ?? $conversation->created_at)->format('d M Y, H:i') }}</strong> <span class="text-muted">· {{ $conversation->message_count }} messages</span></span>
            <i class="fas fa-chevron-down"></i>
        </summary>
        <div class="vi-chat-body" data-vi-feed="messages" data-vi-params='@json(['conversation' => $conversation->id])' data-vi-next="0">
            <div class="vi-feed-more"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </details>
    @endforeach
    @break

@case('messages')
    @foreach($items as $message)
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
    @break
@endswitch
