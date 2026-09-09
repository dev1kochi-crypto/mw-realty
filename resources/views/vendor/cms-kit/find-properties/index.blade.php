@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.filters.index') }}">Filters</a></li>
    <li class="breadcrumb-item active" aria-current="page">Find Properties</li>
@endsection

@section('content')
@php $showLanguageUi = config('cms-kit.common.modules.languages', true); @endphp
<div class="row">
    <div class="col-12">
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-primary">"Find Properties" Section Settings</h6>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    Each card below links to a value of the <strong>Property Type</strong> filter, so make sure the
                    types you want here already exist as options under <a href="{{ route('cms.filters.index') }}">Filters &raquo; Property Type</a>.
                    The property count shown on each card is calculated automatically — it's not something you set.
                </div>
                <form action="{{ route('cms.find-properties.update-section') }}" method="POST">
                    @csrf

                    @if($showLanguageUi)
                    <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="sectionLangTabs" role="tablist">
                        @foreach($languages as $lang)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="section-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#section-panel-{{ $lang->code }}" type="button" role="tab">
                                <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                            </button>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    <div class="tab-content mb-4 language-switcher-content">
                        @foreach($languages as $lang)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="section-panel-{{ $lang->code }}" role="tabpanel">
                            <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $section->translations[$lang->code]['title'] ?? '') }}" placeholder="Find Properties" required>
                            @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @endforeach
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="status" id="sectionStatus" {{ old('status', $section->status ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="sectionStatus">Status</label>
                    </div>

                    <div class="col-12 border-top pt-4 mt-4">
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save me-2"></i>Update Section Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0">Property Type Cards</h5>
                <div class="d-flex gap-2">
                    @if($cmsUser->can('find-properties.delete'))
                    <div class="dropdown" id="bulkActions" style="display: none;">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Bulk Actions (<span id="selectedCount">0</span>)
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('active')"><i class="fas fa-check-circle text-success me-2"></i> Mark Active</button></li>
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('inactive')"><i class="fas fa-times-circle text-secondary me-2"></i> Mark Inactive</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item" type="button" onclick="bulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
                        </ul>
                    </div>
                    @endif
                    @if($cmsUser->can('find-properties.create'))
                    <a href="{{ route('cms.find-properties.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Card
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                <th style="width: 40px;">#</th>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Property Type</th>
                                <th>Properties</th>
                                <th style="width: 100px;">Order</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(function() {
        const table = $('.premium-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('cms.find-properties.index') }}",
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'image', name: 'image', orderable: false, searchable: false},
                {data: 'title', name: 'title'},
                {data: 'property_type', name: 'property_type'},
                {data: 'property_count', name: 'property_count', orderable: false, searchable: false},
                {data: 'order', name: 'order', className: 'text-center'},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[6, 'asc']],
            drawCallback: function() { updateBulkVisibility(); }
        });

        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/find-properties/" + id + "/toggle-status";
            $.post(url, { _token: '{{ csrf_token() }}' }).done(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.find-properties.reorder') }}", { _token: '{{ csrf_token() }}', id: id, order_index: order })
                .done(() => table.ajax.reload(null, false));
        });

        $(document).on('click', '.delete-item', function() {
            if (confirm('Are you sure you want to delete this card?')) {
                const id = $(this).data('id');
                const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/find-properties/" + id;
                $.ajax({ url: url, type: 'DELETE', data: { _token: '{{ csrf_token() }}' }, success: () => table.ajax.reload(null, false) });
            }
        });

        $('#selectAll').on('change', function() {
            $('.row-checkbox').prop('checked', this.checked);
            updateBulkVisibility();
        });
        $(document).on('change', '.row-checkbox', function() { updateBulkVisibility(); });

        function updateBulkVisibility() {
            const checkedCount = $('.row-checkbox:checked').length;
            $('#selectedCount').text(checkedCount);
            $('#bulkActions').toggle(checkedCount > 0);
            $('#selectAll').prop('checked', checkedCount > 0 && checkedCount === $('.row-checkbox').length && $('.row-checkbox').length > 0);
        }

        window.bulkAction = function(action) {
            if (action === 'delete' && !confirm('Are you sure you want to delete selected items?')) return;
            const ids = $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();
            $.post("{{ route('cms.find-properties.bulk-action') }}", { _token: '{{ csrf_token() }}', action: action, ids: ids })
                .done(function() {
                    table.ajax.reload(null, false);
                    $('#selectAll').prop('checked', false);
                    updateBulkVisibility();
                });
        };
    });
</script>
@endpush
