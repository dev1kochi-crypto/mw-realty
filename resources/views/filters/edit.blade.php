@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('portal.properties.index') }}">Properties</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cms.filters.index') }}">Filters</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Filter</li>
@endsection

@section('content')
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Edit Filter</h5>
    </div>
    <div class="card-body p-4">
        @if ($errors->any() && !session('editingValueId'))
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form action="{{ route('cms.filters.update', $filter->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('filters._form')
            <div class="mt-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">Update</button>
                <a href="{{ route('cms.filters.index') }}" class="btn btn-outline-secondary px-4">Back to Filters</a>
            </div>
        </form>
    </div>
</div>

@if($filter->type === 'select')
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Option Values</h5>
        <small class="text-muted">These are the choices shown for this filter on the frontend, and the same list populates the matching dropdown on the Property form.</small>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive mb-4">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Value</th>
                        @foreach($languages as $lang)
                        <th>Label ({{ $lang->code }})</th>
                        @endforeach
                        <th class="text-center" style="width:100px;">Status</th>
                        <th class="text-end" style="width:80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filter->values as $option)
                    <tr>
                        <form action="{{ route('cms.filters.values.update', [$filter->id, $option->id]) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <td>{{ $loop->iteration }}</td>
                            <td><input type="text" name="value" class="form-control form-control-sm" value="{{ $option->value }}" required></td>
                            @foreach($languages as $lang)
                            <td><input type="text" name="translations[{{ $lang->code }}][label]" class="form-control form-control-sm" value="{{ $option->translations[$lang->code]['label'] ?? '' }}" required></td>
                            @endforeach
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input value-toggle-status" type="checkbox" data-filter="{{ $filter->id }}" data-id="{{ $option->id }}" {{ $option->status ? 'checked' : '' }}>
                                </div>
                                <input type="hidden" name="status" value="{{ $option->status ? '1' : '0' }}" class="status-mirror">
                            </td>
                            <td class="text-end">
                                @if($cmsUser->can('filters.edit'))
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Save"><i class="fas fa-save"></i></button>
                                @endif
                                @if($cmsUser->can('filters.delete'))
                                <button type="button" class="btn btn-sm btn-outline-danger value-delete" data-filter="{{ $filter->id }}" data-id="{{ $option->id }}" title="Delete"><i class="fas fa-trash"></i></button>
                                @endif
                            </td>
                        </form>
                    </tr>
                    @empty
                    <tr><td colspan="{{ 4 + count($languages) }}" class="text-center text-muted py-3">No options added yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cmsUser->can('filters.create'))
        <hr>
        <h6 class="fw-bold mb-3">Add Option</h6>
        <form action="{{ route('cms.filters.values.store', $filter->id) }}" method="POST" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Value</label>
                <input type="text" name="value" class="form-control" placeholder="e.g. apartment" required>
            </div>
            @foreach($languages as $lang)
            <div class="col-md-3">
                <label class="form-label">Label ({{ $lang->code }})</label>
                <input type="text" name="translations[{{ $lang->code }}][label]" class="form-control" placeholder="e.g. Apartment" required>
            </div>
            @endforeach
            <div class="col-md-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="status" checked id="newValueStatus">
                    <label class="form-check-label" for="newValueStatus">Active</label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i> Add</button>
            </div>
        </form>
        @endif
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    $(function() {
        $(document).on('change', '.value-toggle-status', function() {
            const filterId = $(this).data('filter');
            const id = $(this).data('id');
            const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/filters/" + filterId + "/values/" + id + "/toggle-status";
            $.post(url, { _token: '{{ csrf_token() }}' }).done(() => location.reload());
        });

        $(document).on('click', '.value-delete', function() {
            if (!confirm('Delete this option?')) return;
            const filterId = $(this).data('filter');
            const id = $(this).data('id');
            const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/filters/" + filterId + "/values/" + id;
            $.ajax({ url: url, type: 'DELETE', data: { _token: '{{ csrf_token() }}' }, success: () => location.reload() });
        });
    });
</script>
@endpush
