@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Help Desk</li>
@endsection

@section('content')

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card glass-card">
    <div class="card-body p-4">
        <div class="p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-0">Help Desk</h5>
                <small class="text-muted">The quick guide agents and companies see when they click the <strong>!</strong> icon on a CRM screen (and the first one opens by itself on their first visit).</small>
            </div>
            @if($cmsUser->can('help-desk.create'))
                <a href="{{ route('cms.help-desk.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Guide</a>
            @endif
        </div>
        <div class="table-responsive pt-4">
            <table class="table premium-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Guide</th>
                        <th>Topic</th>
                        <th class="text-center">Features</th>
                        <th class="text-center">Steps</th>
                        <th class="text-center">Tips</th>
                        <th>Updated</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($guides as $guide)
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-light text-primary flex-shrink-0" style="width: 38px; height: 38px;">
                                    <i class="fas {{ $guide->icon ?: 'fa-circle-info' }}"></i>
                                </span>
                                <div style="min-width: 0;">
                                    <div class="fw-bold">{{ $guide->title }}</div>
                                    @if($guide->intro)
                                        <small class="text-muted d-block text-truncate" style="max-width: 360px;">{{ $guide->intro }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><code class="text-primary">{{ $guide->topic }}</code></td>
                        <td class="text-center">{{ count($guide->features ?? []) }}</td>
                        <td class="text-center">{{ count($guide->steps ?? []) }}</td>
                        <td class="text-center">{{ count($guide->tips ?? []) }}</td>
                        <td class="text-muted small text-nowrap">{{ $guide->updated_at?->format('d M Y') }}</td>
                        <td class="text-center">
                            @if($cmsUser->can('help-desk.edit'))
                                <form action="{{ route('cms.help-desk.toggle-status', $guide->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="badge border-0 {{ $guide->status ? 'bg-success' : 'bg-secondary' }}" title="Click to {{ $guide->status ? 'deactivate' : 'activate' }}">{{ $guide->status ? 'Active' : 'Inactive' }}</button>
                                </form>
                            @else
                                <span class="badge {{ $guide->status ? 'bg-success' : 'bg-secondary' }}">{{ $guide->status ? 'Active' : 'Inactive' }}</span>
                            @endif
                        </td>
                        <td class="text-end pe-4 text-nowrap">
                            @if($cmsUser->can('help-desk.edit'))
                                <a href="{{ route('cms.help-desk.edit', $guide->id) }}" class="btn btn-sm btn-light" title="Edit"><i class="fas fa-pen"></i></a>
                            @endif
                            @if($cmsUser->can('help-desk.delete'))
                                <form action="{{ route('cms.help-desk.destroy', $guide->id) }}" method="POST" class="d-inline" onsubmit="return confirm(@js("Delete the {$guide->title} guide? The ! icon on that screen will stop working — deactivate it instead to hide it for now."));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No help guides yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
