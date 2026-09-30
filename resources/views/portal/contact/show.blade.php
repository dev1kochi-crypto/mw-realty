@extends('portal.layouts.app')

@section('title', $ticket->reference() . ' — ' . $ticket->subject)

@include('portal.contact._styles')

@section('content')
<div class="st-head">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="st-ref">{{ $ticket->reference() }}</span>
            <span class="st-badge st-badge--{{ $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span>
        </div>
        <h1 class="st-head__title mt-1">{{ $ticket->subject }}</h1>
    </div>
    <div class="st-head__actions">
        <a href="{{ route('portal.contact.index') }}" class="btn btn-portal-light btn-sm"><i class="fas fa-arrow-left me-1"></i>My Tickets</a>
        @if(!$ticket->isSolved())
        <form action="{{ route('portal.contact.resolve', $ticket) }}" method="POST" class="m-0"
              data-confirm="Mark this ticket as solved? You can reply later to reopen it."
              data-confirm-title="Resolve support ticket?"
              data-confirm-ok="Mark as Solved"
              data-confirm-tone="primary">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Mark as Solved</button>
        </form>
        @elseif($ticket->status === \App\Models\SupportTicket::RESOLVED)
        <form action="{{ route('portal.contact.reopen', $ticket) }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-portal-light btn-sm"><i class="fas fa-redo me-1"></i>Reopen</button>
        </form>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="portal-card p-3 p-md-4">
            <div class="portal-section-title">Conversation</div>
            <div class="st-thread">
                @foreach($ticket->messages as $message)
                    @if($message->isSystem())
                    <div class="st-event"><i class="fas fa-history me-1"></i>{{ $message->body }} &middot; {{ $message->created_at->format('d M Y, h:i A') }}</div>
                    @else
                    @php $mine = $message->author_type === \App\Models\SupportTicketMessage::CLIENT; @endphp
                    <div class="st-msg {{ $mine ? 'st-msg--mine' : 'st-msg--support' }}">
                        <span class="st-msg__avatar"><i class="fas {{ $mine ? 'fa-user' : 'fa-headset' }}"></i></span>
                        <div class="st-msg__bubble">
                            <div class="st-msg__who">
                                {{ $mine ? 'You' : $message->authorName() }}
                                <span class="st-msg__when">{{ $message->created_at->format('d M Y, h:i A') }}</span>
                            </div>
                            <div class="st-msg__body">{{ $message->body }}</div>
                            @if($message->attachment_path)
                            <a href="{{ route('portal.contact.attachment', [$ticket, $message->id]) }}" class="st-attach"><i class="fas fa-paperclip"></i>{{ $message->attachment_name ?: 'Attachment' }}</a>
                            @endif
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>

        @if($ticket->isClosed())
        <div class="st-empty mt-3 py-4">
            <div class="fw-bold mb-1">This ticket is closed</div>
            <div class="small">Still need help? <a href="{{ route('portal.contact.create') }}">Raise a new ticket</a>.</div>
        </div>
        @else
        <div class="portal-card p-3 p-md-4 mt-3">
            <div class="portal-section-title">{{ $ticket->isSolved() ? 'Not solved after all? Reply to reopen' : 'Reply' }}</div>
            <form action="{{ route('portal.contact.reply', $ticket) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <textarea name="message" class="form-control mb-2 @error('message') is-invalid @enderror" rows="4" required maxlength="5000" placeholder="Write your reply…">{{ old('message') }}</textarea>
                @error('message')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <input type="file" name="attachment_document" class="form-control form-control-sm @error('attachment_document') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf" style="max-width: 280px;">
                        @error('attachment_document')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-portal-primary"><i class="fas fa-paper-plane me-1"></i> Send Reply</button>
                </div>
            </form>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="portal-card p-4">
            <div class="portal-section-title">Ticket Details</div>
            <dl class="st-detail mb-0">
                <dt>Ticket No.</dt><dd class="st-ref">{{ $ticket->reference() }}</dd>
                <dt>Status</dt><dd><span class="st-badge st-badge--{{ $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span></dd>
                <dt>Issue Type</dt><dd>{{ $ticket->categoryLabel() }}</dd>
                <dt>Priority</dt><dd class="st-prio--{{ $ticket->priority }}">{{ $ticket->priorityLabel() }}</dd>
                <dt>Raised</dt><dd>{{ $ticket->created_at->format('d M Y, h:i A') }}</dd>
                <dt>Last Update</dt><dd>{{ ($ticket->last_reply_at ?? $ticket->updated_at)->diffForHumans() }}</dd>
                @if($ticket->resolved_at)
                <dt>Solved</dt><dd class="mb-0">{{ $ticket->resolved_at->format('d M Y, h:i A') }}</dd>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection
