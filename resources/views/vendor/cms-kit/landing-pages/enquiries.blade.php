@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.landing-pages.index') }}">Landing Pages</a></li>
    <li class="breadcrumb-item active" aria-current="page">Enquiries</li>
@endsection

@section('content')
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Landing Page</label>
                <select name="page_source" id="filterSource" class="form-select form-select-sm">
                    <option value="All">All pages</option>
                    @foreach($sources as $source)
                    <option value="{{ $source }}">{{ Illuminate\Support\Str::after($source, 'Landing Page: ') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">From date</label>
                <input type="date" name="from_date" id="filterFromDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">To date</label>
                <input type="date" name="to_date" id="filterToDate" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button type="button" id="applyFilter" class="btn btn-sm btn-outline-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">Landing Page Enquiries</h5>
        @if($cmsUser->can('landing-pages.delete'))
        <div class="dropdown" id="bulkActions" style="display: none;">
            <button class="btn btn-outline-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Bulk Actions (<span id="selectedCount">0</span>)
            </button>
            <ul class="dropdown-menu">
                <li><button class="dropdown-item" type="button" onclick="bulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
            </ul>
        </div>
        @endif
    </div>
    <div class="card-body p-4">
        @if(!$hasData)
        <p class="text-muted text-center py-4 mb-0">No landing page enquiries yet.</p>
        @else
        <div class="table-responsive">
            <table class="table premium-table mb-0 w-100">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Landing Page</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<!-- View Enquiry Modal -->
<div class="modal fade" id="viewEnquiryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enquiry Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="enquiryDetails">
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
        @if($hasData)
        const table = $('.premium-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('cms.landing-pages.enquiries') }}",
                data: function(d) {
                    d.page_source = $('#filterSource').val();
                    d.from_date = $('#filterFromDate').val();
                    d.to_date = $('#filterToDate').val();
                }
            },
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name', render: d => d || '-'},
                {data: 'email', name: 'email', render: d => d || '-'},
                {data: 'phone', name: 'phone', render: d => d || '-'},
                {data: 'source_page', name: 'source_page'},
                {data: 'message', name: 'message', render: function(data) {
                    return data ? `<span class="text-truncate d-inline-block" style="max-width: 200px;">${data}</span>` : '-';
                }},
                {data: 'date', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[7, 'desc']],
            drawCallback: function() {
                updateBulkVisibility();
            }
        });

        $('#applyFilter').on('click', function() {
            table.ajax.reload();
        });
        @endif

        $(document).on('click', '.view-enquiry', function() {
            const id = $(this).data('id');
            $.get(`{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/enquiries/${id}`)
            .done(function(data) {
                const escapeText = value => $('<span>').text(String(value ?? '')).html();
                let html = '<div class="row g-3">';

                const fields = {
                    'name': 'Name',
                    'email': 'Email',
                    'phone': 'Phone',
                    'company': 'Company',
                    'country': 'Country',
                    'page_source': 'Landing Page',
                    'page_url': 'Page URL',
                    'message': 'Message',
                    'created_at': 'Date'
                };

                for (let key in fields) {
                    if (data[key]) {
                        let val = escapeText(data[key]);
                        html += `<div class="col-md-6"><p><strong>${fields[key]}:</strong><br>${val || '-'}</p></div>`;
                    }
                }

                if (data.extra_fields && Object.keys(data.extra_fields).length > 0) {
                    for (let key in data.extra_fields) {
                        if (!fields[key]) {
                            let label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                            html += `<div class="col-md-6"><p><strong>${escapeText(label)}:</strong><br>${escapeText(data.extra_fields[key] || '-')}</p></div>`;
                        }
                    }
                }

                html += '</div>';
                $('#enquiryDetails').html(html);
                $('#viewEnquiryModal').modal('show');
            });
        });

        $(document).on('click', '.delete-item', function() {
            if (confirm('Are you sure you want to delete this enquiry?')) {
                const id = $(this).data('id');
                $.ajax({
                    url: `{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/enquiries/${id}`,
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
            if (action === 'delete' && !confirm('Are you sure you want to delete selected enquiries?')) {
                return;
            }

            const ids = $('.row-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            $.post("{{ route('cms.enquiries.bulk-action') }}", {
                _token: '{{ csrf_token() }}',
                action: action,
                ids: ids
            })
            .done(function() {
                table.ajax.reload(null, false);
                $('#selectAll').prop('checked', false);
                updateBulkVisibility();
            });
        };
    });
</script>
@endpush
