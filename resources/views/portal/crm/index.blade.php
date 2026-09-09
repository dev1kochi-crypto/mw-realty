@extends('portal.layouts.app')

@section('title', 'CRM')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">{{ $isAdmin ? 'All Enquiries (CRM)' : 'My Enquiries (CRM)' }}</div>
    <div class="d-flex gap-2">
        <a href="{{ route('portal.crm.index') }}" class="portal-btn-ghost btn btn-sm @if(!request('status')) active @endif">All</a>
        <a href="{{ route('portal.crm.index', ['status' => 'new']) }}" class="portal-btn-ghost btn btn-sm">New</a>
        <a href="{{ route('portal.crm.index', ['status' => 'contacted']) }}" class="portal-btn-ghost btn btn-sm">Contacted</a>
        <a href="{{ route('portal.crm.index', ['status' => 'closed']) }}" class="portal-btn-ghost btn btn-sm">Closed</a>
    </div>
</div>

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Property</th>
                    @if($isAdmin)
                    <th>Owner</th>
                    @endif
                    <th>Contact</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($enquiries as $enquiry)
                <tr>
                    <td class="fw-semibold">{{ $enquiry->name ?: 'Unknown' }}</td>
                    <td>{{ $enquiry->property?->getTranslation('title') ?? '-' }}</td>
                    @if($isAdmin)
                    <td>
                        @if($enquiry->owner)
                            {{ $enquiry->owner->type === 'company' ? ($enquiry->owner->company_name ?: $enquiry->owner->name) : $enquiry->owner->name }}
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    @endif
                    <td>
                        <div>{{ $enquiry->email }}</div>
                        <div class="text-muted" style="font-size:0.8rem;">{{ $enquiry->phone }}</div>
                    </td>
                    <td>{{ $enquiry->created_at->format('d M Y') }}</td>
                    <td><span class="portal-badge-status portal-badge-{{ $enquiry->status }}">{{ ucfirst($enquiry->status) }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('portal.crm.show', $enquiry->id) }}" class="portal-btn-ghost btn btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $isAdmin ? 7 : 6 }}" class="portal-empty">No enquiries yet. They'll show up here once a visitor enquires about one of your listings.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $enquiries->links() }}</div>
@endsection
