@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Unassigned Leads</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-primary">Unassigned Leads</h6>
        <small class="text-muted">Property enquiries where the listing has no agent or company assigned yet — pick who should handle each one.</small>
    </div>
    <div class="card-body p-4">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($leads->isEmpty())
            <p class="text-muted mb-0">No unassigned leads right now.</p>
        @else
        <div class="table-responsive">
            <table class="table premium-table mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Property</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th class="text-end pe-4">Assign to</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads as $lead)
                    <tr>
                        <td>{{ $lead->name }}</td>
                        <td>
                            @if($lead->email)<div>{{ $lead->email }}</div>@endif
                            @if($lead->formatted_phone)<div class="text-muted small">{{ $lead->formatted_phone }}</div>@endif
                        </td>
                        <td>{{ $lead->property?->getTranslation('title') ?? '—' }}</td>
                        <td><span class="text-truncate d-inline-block" style="max-width: 220px;">{{ $lead->message }}</span></td>
                        <td>{{ $lead->created_at->format('d M Y, h:i A') }}</td>
                        <td class="text-end">
                            <form action="{{ route('cms.unassigned-leads.assign', $lead->id) }}" method="POST" class="d-flex gap-2 justify-content-end">
                                @csrf
                                <select name="portal_user_id" class="form-select form-select-sm" style="width: auto;" required>
                                    <option value="">Select agent/company…</option>
                                    @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->type === 'company' ? $agent->company_name : $agent->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Assign</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $leads->links() }}</div>
        @endif
    </div>
</div>
@endsection
