@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Home Banners</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">Home Banners</h5>
        <div class="d-flex gap-2">
            <div id="bulkActions" style="display: none;">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs"></i> Bulk Actions (<span id="selectedCount">0</span>)
                    </button>
                    <ul class="dropdown-menu">
                        <li><button class="dropdown-item" onclick="bulkAction('activate')"><i class="fas fa-check-circle text-success me-2"></i> Activate Selected</button></li>
                        <li><button class="dropdown-item" onclick="bulkAction('deactivate')"><i class="fas fa-times-circle text-secondary me-2"></i> Deactivate Selected</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-danger" onclick="bulkAction('delete')"><i class="fas fa-trash me-2"></i> Delete Selected</button></li>
                    </ul>
                </div>
            </div>
            <a
                href="{{ route('cms.banners.create') }}"
                id="addBannerButton"
                class="btn btn-primary btn-sm {{ ($canAddBanner ?? true) && $cmsUser->can('banners.edit') ? '' : 'd-none' }}"
            >
                <i class="fas fa-plus"></i> Add Banner
            </a>
            <span
                id="bannerLimitBadge"
                class="badge bg-warning text-dark {{ !($canAddBanner ?? true) ? '' : 'd-none' }}"
            >
                <i class="fas fa-info-circle"></i> Limit Reached ({{ $maxBanners }})
            </span>
        </div>
    </div>
    <div class="card-body p-4">
        <div id="bannerToastArea" class="mb-3"></div>

        <div class="table-responsive">
            <table class="table premium-table mb-0 w-100">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th>#</th>
                        <th>Image</th>
                        <th>Line 1 (Default)</th>
                        <th>Order</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
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
        const maxBanners = {{ (int) $maxBanners }};
        const canEditBanners = {{ $cmsUser->can('banners.edit') ? 'true' : 'false' }};
        const table = $('.premium-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('cms.banners.index') }}",
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'media', name: 'media', orderable: false, searchable: false},
                {data: 'localized_title', name: 'localized_title'},
                {data: 'order', name: 'order'},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[1, 'asc']],
            drawCallback: function() {
                updateBulkVisibility();
                updateBannerLimitState();
            }
        });

        function updateBannerLimitState() {
            const pageInfo = table.page.info();
            const totalRecords = Number(pageInfo.recordsTotal || 0);
            const limitReached = totalRecords >= maxBanners;

            if (canEditBanners) {
                $('#addBannerButton').toggleClass('d-none', limitReached);
            }

            $('#bannerLimitBadge').toggleClass('d-none', !limitReached);
        }

        // Toggle Status
        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            $.post("{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/banners/" + id + "/toggle-status", {
                _token: '{{ csrf_token() }}'
            }).done(() => table.ajax.reload(null, false));
        });

        // Reorder
        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.banners.reorder') }}", {
                _token: '{{ csrf_token() }}',
                id: id,
                order_index: order
            }).done(() => table.ajax.reload(null, false));
        });

        // Delete
        $(document).on('click', '.delete-item', function() {
            if (confirm('Are you sure you want to delete this banner?')) {
                const id = $(this).data('id');
                $.ajax({
                    url: "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/banners/" + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: () => table.ajax.reload(null, false)
                });
            }
        });

        // Bulk Actions
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
            $('#selectAll').prop('checked', checkedCount > 0 && checkedCount === $('.row-checkbox').length);
        }

        function showBannerToast(message) {
            const toastId = `banner-toast-${Date.now()}`;
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center border-0 shadow-sm w-100" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex bg-white rounded">
                        <div class="toast-body d-flex align-items-center gap-2 text-success fw-medium">
                            <i class="fas fa-check-circle"></i>
                            <span>${message}</span>
                        </div>
                        <button type="button" class="btn-close me-3 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

            const toastArea = $('#bannerToastArea');
            toastArea.html(`
                <div class="d-flex justify-content-end">
                    <div style="max-width: 420px; width: 100%;">
                        ${toastHtml}
                    </div>
                </div>
            `);

            const toastEl = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastEl, {
                delay: 3000
            });

            toastEl.addEventListener('hidden.bs.toast', function() {
                toastArea.empty();
            });

            toast.show();
        }

        window.bulkAction = function(action) {
            const checkedBoxes = $('.row-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one banner.');
                return;
            }

            let confirmMessage = 'Are you sure you want to ';
            let successMessage = '';
            if (action === 'delete') {
                confirmMessage += 'delete the selected banners?';
                successMessage = 'Banners deleted successfully!';
            } else if (action === 'activate') {
                confirmMessage += 'activate the selected banners?';
                successMessage = 'Banners activated successfully!';
            } else if (action === 'deactivate') {
                confirmMessage += 'deactivate the selected banners?';
                successMessage = 'Banners deactivated successfully!';
            }

            if (confirm(confirmMessage)) {
                const ids = checkedBoxes.map(function() {
                    return $(this).val();
                }).get();

                $.ajax({
                    url: "{{ route('cms.banners.bulk-action') }}",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids,
                        action: action
                    },
                    success: function(response) {
                        if (response.success) {
                            showBannerToast(successMessage);

                            table.ajax.reload(null, false);

                            $('#selectAll').prop('checked', false);
                            $('.row-checkbox').prop('checked', false);
                            updateBulkVisibility();
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            }
        };
    });
</script>
@endpush
