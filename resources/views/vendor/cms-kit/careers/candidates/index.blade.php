@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Candidates</li>
@endsection

@section('content')
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end">
            @if($columns['apply_for'] ?? true)
            <div class="col-md-3">
                <label class="form-label small fw-bold">Apply for</label>
                <select name="apply_for" id="filterApplyFor" class="form-select form-select-sm">
                    <option value="All">All</option>
                    @foreach($applyForOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if($columns['state'] ?? true)
            <div class="col-md-2">
                <label class="form-label small fw-bold">State</label>
                <select name="state" id="filterState" class="form-select form-select-sm">
                    <option value="All">All</option>
                    @foreach($stateOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if($columns['country'] ?? true)
            <div class="col-md-2">
                <label class="form-label small fw-bold">Country</label>
                <select name="country" id="filterCountry" class="form-select form-select-sm">
                    <option value="All">All</option>
                    @foreach($countryOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if($columns['submitted_at'] ?? true)
            <div class="col-md-2">
                <label class="form-label small fw-bold">From date</label>
                <input type="date" name="from_date" id="filterFromDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">To date</label>
                <input type="date" name="to_date" id="filterToDate" class="form-control form-control-sm">
            </div>
            @endif
            <div class="col-auto">
                <button type="button" id="applyFilter" class="btn btn-sm btn-outline-primary">Filter</button>
                @if($hasData && $cmsUser->can('careers.export'))
                <button type="button" id="exportCsv" class="btn btn-sm btn-success">Export CSV</button>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">Candidates List</h5>
        @if($cmsUser->can('careers.delete'))
        <div class="dropdown" id="bulkActions" style="display: none;">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Bulk Actions (<span id="selectedCount">0</span>)
            </button>
            <ul class="dropdown-menu">
                <li><button class="dropdown-item" type="button" onclick="bulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
            </ul>
        </div>
        @endif
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table premium-table table-hover align-middle mb-0 w-100" id="careerCandidatesTable">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                        <th>#</th>
                        @foreach($columns as $key => $show)
                            @if($show)
                            <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                            @endif
                        @endforeach
                        @foreach(config('cms-kit.database.careers.candidates.extra_fields', []) as $key => $field)
                        <th>{{ $field['label'] ?? ucfirst(str_replace('_', ' ', $key)) }}</th>
                        @endforeach
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script>
    $(function() {
        const table = $('#careerCandidatesTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            scrollX: true,
            ajax: {
                url: "{{ route('cms.careers.candidates.index') }}",
                data: function(d) {
                    @if($columns['apply_for'] ?? true)
                    d.apply_for = $('#filterApplyFor').val();
                    @endif
                    @if($columns['state'] ?? true)
                    d.state = $('#filterState').val();
                    @endif
                    @if($columns['country'] ?? true)
                    d.country = $('#filterCountry').val();
                    @endif
                    @if($columns['submitted_at'] ?? true)
                    d.from_date = $('#filterFromDate').val();
                    d.to_date = $('#filterToDate').val();
                    @endif
                }
            },
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                @foreach($columns as $key => $show)
                    @if($show)
                    {data: '{{ $key }}', name: '{{ $key }}'},
                    @endif
                @endforeach
                @foreach(config('cms-kit.database.careers.candidates.extra_fields', []) as $key => $field)
                {data: '{{ $key }}', name: '{{ $key }}', orderable: false},
                @endforeach
                {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end pe-4'}
            ],
            order: [[{{ count(array_filter($columns)) + 1 }}, 'desc']],
            drawCallback: function() {
                updateBulkVisibility();
            }
        });

        $('#applyFilter').on('click', function() {
            table.ajax.reload();
        });

        $('#exportCsv').on('click', function() {
            const params = $.param({
                @if($columns['apply_for'] ?? true)
                apply_for: $('#filterApplyFor').val(),
                @endif
                @if($columns['state'] ?? true)
                state: $('#filterState').val(),
                @endif
                @if($columns['country'] ?? true)
                country: $('#filterCountry').val(),
                @endif
                @if($columns['submitted_at'] ?? true)
                from_date: $('#filterFromDate').val(),
                to_date: $('#filterToDate').val()
                @endif
            });
            window.location.href = "{{ route('cms.careers.candidates.export') }}?" + params;
        });

        $(document).on('click', '.delete-item', function() {
            if (confirm('Delete this candidate?')) {
                const id = $(this).data('id');
                $.ajax({
                    url: `{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/careers/candidates/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function() {
                        table.ajax.reload(null, false);
                    }
                });
            }
        });

        $('#selectAll').on('change', function() {
            $('.row-checkbox').prop('checked', this.checked);
            updateBulkVisibility();
        });

        $(document).on('change', '.row-checkbox', function() {
            updateBulkVisibility();
        });

        function updateBulkVisibility() {
            const checkedCount = $('.row-checkbox:checked').length;
            $('#selectedCount').text(checkedCount);
            $('#bulkActions').toggle(checkedCount > 0);
            $('#selectAll').prop('checked', checkedCount > 0 && checkedCount === $('.row-checkbox').length && $('.row-checkbox').length > 0);
        }

        window.bulkAction = function(action) {
            if (action === 'delete' && !confirm('Delete selected candidates?')) {
                return;
            }

            const ids = $('.row-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            $.post("{{ route('cms.careers.candidates.bulk-action') }}", {
                _token: '{{ csrf_token() }}',
                action: action,
                ids: ids
            }).done(function() {
                table.ajax.reload(null, false);
                $('#selectAll').prop('checked', false);
                updateBulkVisibility();
            });
        };
    });
</script>
@endpush
