@extends('portal.layouts.app')

@section('title', 'Nearby Places')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <div class="portal-section-title mb-1">Nearby Places</div>
        <p class="text-muted small mb-0">
            Landmarks (schools, hospitals, restaurants, attractions) your properties can be tagged with.
            @unless($isAdmin) Shared places come from MW Realty; add your own for anything missing. @endunless
        </p>
    </div>
    <a href="{{ route('portal.nearby-places.create') }}" class="btn btn-portal-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Add Place
    </a>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<ul class="nav portal-lang-tabs mb-3">
    @foreach(array_filter(['all' => 'All', 'mine' => $ownerId ? 'My Places' : null, 'shared' => 'Shared']) as $key => $label)
    <li class="nav-item">
        <a class="nav-link {{ $scope === $key ? 'active' : '' }}" href="{{ route('portal.nearby-places.index', $key === 'all' ? [] : ['scope' => $key]) }}">{{ $label }}</a>
    </li>
    @endforeach
</ul>

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Address</th>
                    <th>Added By</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($places as $place)
                @php $canManage = $isAdmin || $place->portal_user_id === $ownerId; @endphp
                <tr>
                    <td class="fw-semibold">{{ $place->getTranslation('name') }}</td>
                    <td>{{ $place->typeLabel() ?? '—' }}</td>
                    <td>{{ $place->getTranslation('address') ?? '—' }}</td>
                    <td>
                        @if($place->isShared())
                            <span class="badge bg-light text-dark border"><i class="fas fa-globe me-1"></i>Shared</span>
                        @elseif($place->portal_user_id === $ownerId)
                            <span class="badge bg-primary-subtle text-primary border"><i class="fas fa-user me-1"></i>You</span>
                        @else
                            {{ $place->owner?->displayName() ?? '—' }}
                        @endif
                    </td>
                    <td class="text-center">
                        @if($canManage)
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input toggle-status" type="checkbox" data-id="{{ $place->id }}" {{ $place->status ? 'checked' : '' }}>
                        </div>
                        @else
                        <span class="small {{ $place->status ? 'text-success' : 'text-secondary' }}">{{ $place->status ? 'Active' : 'Inactive' }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($canManage)
                        <a href="{{ route('portal.nearby-places.edit', $place->id) }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-edit"></i></a>
                        <button type="button" class="portal-btn-ghost btn btn-sm delete-place" data-id="{{ $place->id }}"><i class="fas fa-trash"></i></button>
                        @else
                        <span class="portal-muted small" title="Managed by MW Realty"><i class="fas fa-lock"></i></span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="portal-empty">No nearby places yet. <a href="{{ route('portal.nearby-places.create') }}">Add your first one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $places->links('pagination::bootstrap-5') }}</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('change', function (e) {
        const toggle = e.target.closest('.toggle-status');
        if (!toggle) return;
        fetch("{{ url('portal/nearby-places') }}/" + toggle.dataset.id + "/toggle-status", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        }).then(r => { if (!r.ok) throw new Error(); })
          .catch(() => { toggle.checked = !toggle.checked; alert('Could not update the status.'); });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.delete-place');
        if (!btn) return;
        if (!confirm('Delete this nearby place? Any properties tagged with it will lose that tag.')) return;
        fetch("{{ url('portal/nearby-places') }}/" + btn.dataset.id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        }).then(r => r.json()).then(function (data) {
            if (data.success) { window.location.reload(); }
        });
    });
</script>
@endpush
