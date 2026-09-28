@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.support-tickets.index') }}" class="text-decoration-none text-muted">Support Tickets</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $ticket->reference() }}</li>
@endsection

@include('cms-kit::support-tickets._styles')

@section('content')
@php $canEdit = $cmsUser->can('support-tickets.edit'); @endphp

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <span class="tk-ref">{{ $ticket->reference() }}</span>
            <span class="badge tk-status--{{ $ticket->statusTone() }}">{{ $ticket->statusLabel(true) }}</span>
        </div>
        <h4 class="mb-0 mt-1">{{ $ticket->subject }}</h4>
    </div>
    <a href="{{ route('cms.support-tickets.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> All Tickets</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="mb-0">Conversation</h6></div>
            <div class="card-body tk-thread">
                @foreach($ticket->messages as $message)
                    @if($message->isSystem())
                    <div class="tk-event"><i class="fas fa-history me-1"></i>{{ $message->body }} &middot; {{ $message->created_at->format('d M Y, h:i A') }}</div>
                    @else
                    @php $isAdmin = $message->author_type === \App\Models\SupportTicketMessage::ADMIN; @endphp
                    <div class="tk-msg {{ $isAdmin ? 'tk-msg--admin' : '' }}">
                        <span class="tk-msg-avatar"><i class="fas {{ $isAdmin ? 'fa-headset' : 'fa-user' }}"></i></span>
                        <div class="tk-msg-bubble">
                            <div class="tk-msg-who">{{ $message->authorName(true) }}<span class="tk-msg-when">{{ $message->created_at->format('d M Y, h:i A') }}</span></div>
                            <div class="tk-msg-body">{{ $message->body }}</div>
                            @if($message->attachment_path)
                            <a href="{{ route('cms.support-tickets.attachment', [$ticket, $message->id]) }}" class="tk-attach"><i class="fas fa-paperclip"></i>{{ $message->attachment_name ?: 'Attachment' }}</a>
                            @endif
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>

        @if($canEdit)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white py-3"><h6 class="mb-0">Reply to Client</h6></div>
            <div class="card-body">
                <form action="{{ route('cms.support-tickets.reply', $ticket) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <textarea name="message" class="form-control mb-3" rows="5" required maxlength="5000" placeholder="Write your reply — the client sees this as “MW Realty Support”.">{{ old('message') }}</textarea>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold mb-1">Attachment (optional)</label>
                            <input type="file" name="attachment_document" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Status after reply</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Automatic (Awaiting Client)</option>
                                @foreach(\App\Models\SupportTicket::STATUSES as $key => [$label])
                                <option value="{{ $key }}">{{ $key === 'awaiting_client' ? 'Awaiting Client' : $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 text-md-end">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-paper-plane me-1"></i> Send Reply</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3"><h6 class="mb-0">Ticket Details</h6></div>
            <div class="card-body">
                @if($canEdit)
                <form action="{{ route('cms.support-tickets.update', $ticket) }}" method="POST" class="mb-3">
                    @csrf
                    @method('PUT')
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm mb-2">
                        @foreach(\App\Models\SupportTicket::STATUSES as $key => [$label])
                        <option value="{{ $key }}" @selected($ticket->status === $key)>{{ $key === 'awaiting_client' ? 'Awaiting Client' : $label }}</option>
                        @endforeach
                    </select>
                    <label class="form-label small fw-semibold mb-1">Priority</label>
                    <select name="priority" class="form-select form-select-sm mb-2">
                        @foreach(\App\Models\SupportTicket::PRIORITIES as $key => $label)
                        <option value="{{ $key }}" @selected($ticket->priority === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Update Ticket</button>
                </form>
                @endif
                <dl class="tk-detail mb-0">
                    <dt>Issue Type</dt><dd>{{ $ticket->categoryLabel() }}</dd>
                    <dt>Priority</dt><dd class="tk-prio tk-prio--{{ $ticket->priority }}">{{ $ticket->priorityLabel() }}</dd>
                    <dt>Raised</dt><dd>{{ $ticket->created_at->format('d M Y, h:i A') }}</dd>
                    <dt>Last Update</dt><dd>{{ ($ticket->last_reply_at ?? $ticket->updated_at)->diffForHumans() }} by {{ $ticket->last_reply_by === 'admin' ? 'Support' : 'Client' }}</dd>
                    @if($ticket->resolved_at)<dt>Solved</dt><dd>{{ $ticket->resolved_at->format('d M Y, h:i A') }}</dd>@endif
                    @if($ticket->closed_at)<dt>Closed</dt><dd class="mb-0">{{ $ticket->closed_at->format('d M Y, h:i A') }}</dd>@endif
                </dl>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="mb-0">Client</h6></div>
            <div class="card-body small">
                @if($client = $ticket->portalUser)
                <div class="fw-bold">{{ $client->displayName() }}</div>
                <div class="text-muted mb-2">{{ $client->type === 'company' ? 'Company' : 'Agent' }}</div>
                <div><i class="fas fa-envelope me-2 text-muted"></i>{{ $client->email }}</div>
                @if($client->phone)<div><i class="fas fa-phone me-2 text-muted"></i>{{ $client->phone }}</div>@endif
                <a href="{{ route('cms.portal-accounts.show', $client->id) }}" class="btn btn-sm btn-outline-secondary w-100 mt-3">View Account</a>
                @endif

                @if($otherTickets->isNotEmpty())
                <div class="fw-semibold mt-3 mb-1">Other tickets from this client</div>
                @foreach($otherTickets as $other)
                <a href="{{ route('cms.support-tickets.show', $other) }}" class="d-flex justify-content-between text-decoration-none py-1 border-top">
                    <span class="text-truncate me-2"><span class="tk-ref">{{ $other->reference() }}</span> {{ $other->subject }}</span>
                    <span class="badge tk-status--{{ $other->statusTone() }}">{{ $other->statusLabel(true) }}</span>
                </a>
                @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
