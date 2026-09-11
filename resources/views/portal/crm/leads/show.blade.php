@extends('portal.crm._layout')


@section('title', 'Lead Detail')

@section('crm-content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">Lead from {{ $lead->name ?: 'Unknown' }}</div>
    <a href="{{ route('portal.crm.leads.index') }}" class="portal-btn-ghost btn btn-sm">Back to Leads</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="portal-card p-4 mb-3">
            <div class="portal-section-title">Lead Details</div>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted">Name</dt>
                <dd class="col-sm-8">{{ $lead->name ?: '-' }}</dd>
                <dt class="col-sm-4 text-muted">Email</dt>
                <dd class="col-sm-8">{{ $lead->email ?: '-' }}</dd>
                <dt class="col-sm-4 text-muted">Phone</dt>
                <dd class="col-sm-8">{{ $lead->phone ?: '-' }}</dd>
                <dt class="col-sm-4 text-muted">Property</dt>
                <dd class="col-sm-8">{{ $lead->property?->getTranslation('title') ?? '-' }}</dd>
                @if($isAdmin)
                <dt class="col-sm-4 text-muted">Owner</dt>
                <dd class="col-sm-8">
                    @if($lead->owner)
                        {{ $lead->owner->type === 'company' ? ($lead->owner->company_name ?: $lead->owner->name) : $lead->owner->name }}
                    @else
                        -
                    @endif
                </dd>
                @endif
                <dt class="col-sm-4 text-muted">Received</dt>
                <dd class="col-sm-8">{{ $lead->created_at->format('d M Y, H:i') }}</dd>
                <dt class="col-sm-4 text-muted">Source</dt>
                <dd class="col-sm-8">{{ $lead->page_source ?: '-' }}</dd>
                <dt class="col-sm-4 text-muted">Message</dt>
                <dd class="col-sm-8">{{ $lead->message ?: '-' }}</dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="portal-card p-4">
            <div class="portal-section-title">Manage Lead</div>
            <form action="{{ route('portal.crm.leads.update', $lead->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="new" {{ $lead->status === 'new' ? 'selected' : '' }}>New</option>
                        <option value="contacted" {{ $lead->status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                        <option value="closed" {{ $lead->status === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Stage</label>
                    <select name="stage_id" class="form-select">
                        <option value="">No stage</option>
                        @foreach($stages as $stage)
                        <option value="{{ $stage->id }}" {{ (int) $lead->stage_id === $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Source</label>
                    <select name="source_id" class="form-select">
                        <option value="">No source</option>
                        @foreach($sources as $source)
                        <option value="{{ $source->id }}" {{ (int) $lead->source_id === $source->id ? 'selected' : '' }}>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tags</label>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse($tags as $tag)
                        @php($checked = $lead->tags->contains('id', $tag->id))
                        <label class="portal-tag-chip-check" style="border-color: {{ $tag->color }};">
                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ $checked ? 'checked' : '' }}>
                            {{ $tag->name }}
                        </label>
                        @empty
                        <span class="text-muted small">No tags yet — add some under Master &gt; Tag.</span>
                        @endforelse
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="5" placeholder="Follow-up notes...">{{ old('notes', $lead->notes) }}</textarea>
                </div>
                <button type="submit" class="btn btn-portal-primary w-100">Save Changes</button>
            </form>
        </div>
    </div>
</div>
@endsection
