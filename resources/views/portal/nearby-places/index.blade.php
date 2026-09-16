@extends('portal.layouts.app')

@section('title', 'Nearby Places')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <div class="portal-section-title mb-1">Nearby Places</div>
        <p class="text-muted small mb-0">Landmarks (schools, hospitals, restaurants, attractions) properties can be tagged with.</p>
    </div>
    <a href="{{ route('portal.nearby-places.create') }}" class="btn btn-portal-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Add Place
    </a>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="portal-card p-4">
    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Address</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($places as $place)
                <tr>
                    <td class="fw-semibold">{{ $place->getTranslation('name') }}</td>
                    <td>{{ $place->typeLabel() ?? '—' }}</td>
                    <td>{{ $place->getTranslation('address') ?? '—' }}</td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input toggle-status" type="checkbox" data-id="{{ $place->id }}" {{ $place->status ? 'checked' : '' }}>
                        </div>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('portal.nearby-places.edit', $place->id) }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-edit"></i></a>
                        <button type="button" class="portal-btn-ghost btn btn-sm delete-place" data-id="{{ $place->id }}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="portal-empty">No nearby places yet. <a href="{{ route('portal.nearby-places.create') }}">Add your first one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $places->links() }}</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('change', function (e) {
        const toggle = e.target.closest('.toggle-status');
        if (!toggle) return;
        fetch("{{ url('portal/nearby-places') }}/" + toggle.dataset.id + "/toggle-status", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        });
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
