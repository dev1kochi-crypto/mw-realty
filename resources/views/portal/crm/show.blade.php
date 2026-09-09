@extends('portal.layouts.app')

@section('title', 'Enquiry Detail')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">Enquiry from {{ $enquiry->name ?: 'Unknown' }}</div>
    <a href="{{ route('portal.crm.index') }}" class="portal-btn-ghost btn btn-sm">Back to CRM</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="portal-card p-4 mb-3">
            <div class="portal-section-title">Lead Details</div>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted">Name</dt>
                <dd class="col-sm-8">{{ $enquiry->name ?: '-' }}</dd>
                <dt class="col-sm-4 text-muted">Email</dt>
                <dd class="col-sm-8">{{ $enquiry->email ?: '-' }}</dd>
                <dt class="col-sm-4 text-muted">Phone</dt>
                <dd class="col-sm-8">{{ $enquiry->phone ?: '-' }}</dd>
                <dt class="col-sm-4 text-muted">Property</dt>
                <dd class="col-sm-8">{{ $enquiry->property?->getTranslation('title') ?? '-' }}</dd>
                <dt class="col-sm-4 text-muted">Received</dt>
                <dd class="col-sm-8">{{ $enquiry->created_at->format('d M Y, H:i') }}</dd>
                <dt class="col-sm-4 text-muted">Message</dt>
                <dd class="col-sm-8">{{ $enquiry->message ?: '-' }}</dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="portal-card p-4">
            <div class="portal-section-title">Manage Lead</div>
            <form action="{{ route('portal.crm.update', $enquiry->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="new" {{ $enquiry->status === 'new' ? 'selected' : '' }}>New</option>
                        <option value="contacted" {{ $enquiry->status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                        <option value="closed" {{ $enquiry->status === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="5" placeholder="Follow-up notes...">{{ old('notes', $enquiry->notes) }}</textarea>
                </div>
                <button type="submit" class="btn btn-portal-primary w-100">Save Changes</button>
            </form>
        </div>
    </div>
</div>
@endsection
