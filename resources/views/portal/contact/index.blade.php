@extends('portal.layouts.app')

@section('title', 'Contact Us')

@include('portal.contact._styles')

@section('content')
<div class="st-head">
    <div>
        <h1 class="st-head__title">Contact Us</h1>
        <p class="st-head__sub">Need help with your account, a listing, or billing? Raise a ticket and track it here until it's solved.</p>
    </div>
    <a href="{{ route('portal.contact.create') }}" class="btn btn-portal-primary"><i class="fas fa-plus me-1"></i> Raise a Ticket</a>
</div>

<div class="st-stats">
    @foreach([
        ['Total Tickets', $stats['total'], 'fa-ticket-alt', null],
        ['Open', $stats['active'], 'fa-folder-open', 'background: rgba(4,161,204,0.12); color: #0284a8;'],
        ['Awaiting Your Reply', $stats['awaiting'], 'fa-reply', 'background: rgba(245,158,11,0.15); color: #b45309;'],
        ['Solved', $stats['solved'], 'fa-check-circle', 'background: rgba(22,163,74,0.12); color: #16a34a;'],
    ] as [$label, $value, $icon, $style])
    <div class="st-stat">
        <span class="st-stat__icon" @if($style) style="{{ $style }}" @endif><i class="fas {{ $icon }}"></i></span>
        <div><div class="st-stat__value">{{ $value }}</div><div class="st-stat__label">{{ $label }}</div></div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="portal-card p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div class="portal-section-title mb-0">Ticket History</div>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <select name="category" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="">All issue types</option>
                        @foreach(\App\Models\SupportTicket::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" style="width: 190px;" placeholder="Subject or ticket no.">
                </form>
            </div>

            @php
                $tabs = ['' => 'All', 'active' => 'Open', 'awaiting_client' => 'Awaiting Reply', 'resolved' => 'Solved', 'closed' => 'Closed'];
            @endphp
            <div class="st-tabs mb-3">
                @foreach($tabs as $key => $label)
                <a href="{{ route('portal.contact.index', array_filter(['status' => $key, 'category' => request('category'), 'search' => request('search')])) }}"
                   class="st-tab @if((string) request('status') === $key) is-active @endif">{{ $label }}</a>
                @endforeach
            </div>

            @forelse($tickets as $ticket)
            <a href="{{ route('portal.contact.show', $ticket) }}" class="st-row @if($ticket->status === \App\Models\SupportTicket::AWAITING_CLIENT) st-row--attention @endif">
                <div class="st-row__main">
                    <div class="st-row__subject">{{ $ticket->subject }}</div>
                    <div class="st-row__meta">
                        <span class="st-ref">{{ $ticket->reference() }}</span>
                        <span class="st-chip"><i class="fas fa-tag"></i>{{ $ticket->categoryLabel() }}</span>
                        @if(in_array($ticket->priority, ['high', 'urgent'], true))
                        <span class="st-chip st-prio--{{ $ticket->priority }}"><i class="fas fa-flag"></i>{{ $ticket->priorityLabel() }}</span>
                        @endif
                        <span><i class="far fa-comments me-1"></i>{{ $ticket->replies_count }}</span>
                        <span>Updated {{ ($ticket->last_reply_at ?? $ticket->updated_at)->diffForHumans() }}</span>
                    </div>
                </div>
                <span class="st-badge st-badge--{{ $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span>
            </a>
            @empty
            <div class="st-empty">
                <i class="fas fa-headset d-block"></i>
                <div class="fw-bold mb-1">{{ request()->hasAny(['status', 'category', 'search']) ? 'No tickets match these filters' : 'No tickets yet' }}</div>
                <div class="small">Something not working? <a href="{{ route('portal.contact.create') }}">Raise a ticket</a> and our team will help.</div>
            </div>
            @endforelse

            <div class="mt-3">{{ $tickets->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>

    <div class="col-lg-4">
        @include('portal.contact._contact-card')
    </div>
</div>
@endsection
