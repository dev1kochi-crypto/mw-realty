@extends('cms-kit::layouts.cms')

@php
    $heading = $type === 'agent' ? 'Agents' : ($type === 'company' ? 'Companies' : 'Agents & Companies');
    $addLabel = $type === 'agent' ? 'Add Agent' : ($type === 'company' ? 'Add Company' : 'Add Agent / Company');
    $showCompanyColumn = $type !== 'company';
    $showAgentsColumn = $type !== 'agent';
@endphp

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">{{ $heading }}</li>
@endsection

@push('styles')
<style>
    .editable-cell .cell-edit { display: flex; }
    .editable-cell .cell-edit.d-none { display: none; }
    .editable-cell .cell-display { display: inline-flex; align-items: center; }

    .btn-brand-add {
        background: linear-gradient(135deg, var(--primary-color, #04a1cc), var(--secondary-color, #264373));
        border: none; color: #fff; font-weight: 700; font-size: 0.85rem;
        padding: 0.5rem 1.1rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(4,161,204,0.25);
    }
    .btn-brand-add:hover { color: #fff; opacity: 0.92; }
    .btn-toolbar-outline {
        border: 1.5px solid #e1e4ec; color: #4b5065; font-weight: 700; font-size: 0.85rem;
        padding: 0.5rem 1rem; border-radius: 10px; background: #fff;
    }
    .btn-toolbar-outline:hover { background: #f7f8fc; color: #264373; }
    .btn-toolbar-outline.has-filters { border-color: var(--primary-color, #04a1cc); color: var(--primary-color, #04a1cc); }

    .premium-table thead th { white-space: nowrap; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.02em; color: #838aa3; background: #f9fafc; }
    .premium-table tbody td { vertical-align: middle; font-size: 0.85rem; }
    .premium-table tbody tr:hover { background: #f9fafc; }
    .premium-table { border-collapse: separate; border-spacing: 0; }

    .contact-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 0.72rem; color: #fff;
    }
    .contact-subline { font-size: 0.74rem; line-height: 1.5; }
    .min-w-0 { min-width: 0; }
    .fill-teal  { background: linear-gradient(135deg, var(--primary-color, #04a1cc), #2fc4e8); }
    .fill-navy  { background: linear-gradient(135deg, var(--secondary-color, #264373), #3a5794); }

    #bulkDeleteBtn:disabled { opacity: 0.45; cursor: not-allowed; }

    .reject-modal-content { border: none; border-radius: 20px; box-shadow: 0 24px 60px rgba(28,35,64,0.22); }
    .reject-modal-icon {
        width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 1.1rem;
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        background: rgba(220,53,69,0.1); color: #dc3545;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ $heading }}</h5>
                <div class="d-flex gap-2 flex-wrap">
                    @can('portal-accounts.edit')
                    <button type="button" id="bulkApproveBtn" class="btn btn-sm btn-outline-success" disabled>
                        <i class="fas fa-check me-1"></i> Approve Selected (<span id="bulkApproveCount">0</span>)
                    </button>
                    @endcan
                    @can('portal-accounts.delete')
                    <button type="button" id="bulkDeleteBtn" class="btn btn-sm btn-outline-danger" disabled>
                        <i class="fas fa-trash me-1"></i> Delete Selected (<span id="bulkDeleteCount">0</span>)
                    </button>
                    @endcan
                    <button type="button" class="btn btn-sm btn-toolbar-outline" id="openFiltersBtn" data-bs-toggle="offcanvas" data-bs-target="#filtersPanel">
                        <i class="fas fa-filter me-1"></i> Filters
                        <span id="filterCountBadge" class="badge rounded-pill bg-primary ms-1 d-none">0</span>
                    </button>
                    <a href="#" id="exportBtn" class="btn btn-sm btn-toolbar-outline"><i class="fas fa-file-excel me-1"></i> Export</a>
                    @can('portal-accounts.edit')
                    <a href="{{ route('cms.portal-accounts.create', array_filter(['type' => $type])) }}" class="btn btn-sm btn-brand-add"><i class="fas fa-plus me-1"></i> {{ $addLabel }}</a>
                    @endcan
                </div>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    Agents and companies can register their own account at <code>/portal/register</code>, or you can add one
                    directly above. Self-registered accounts start <strong>pending</strong> until approved here — only
                    approved accounts can log in and list properties.
                </div>
                <div id="activeFiltersBar" class="alert d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3 mb-3 d-none" style="background:#eaf7fb; border:1px solid #b8e6f2;">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <i class="fas fa-filter text-primary"></i>
                        <span class="fw-bold small text-nowrap">Filters applied:</span>
                        <div id="activeFilterChips" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <button type="button" id="clearFiltersBtn" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="fas fa-times me-1"></i>Clear all</button>
                </div>
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100">
                        <thead>
                            <tr>
                                <th style="width: 36px;"><input type="checkbox" id="selectAllRows" class="form-check-input"></th>
                                <th style="width: 40px;">#</th>
                                <th style="min-width: 200px;">Contact</th>
                                @if(!$type)
                                <th>Type</th>
                                @endif
                                @if($showCompanyColumn)
                                <th>Company</th>
                                @endif
                                @if($showAgentsColumn)
                                <th>Agents</th>
                                @endif
                                <th>Properties</th>
                                <th>Leads</th>
                                <th>Plan</th>
                                <th>Payment</th>
                                <th>Admin Approval</th>
                                <th class="text-center">Active</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="filtersPanel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Filter {{ $heading }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-3">
        @if($showCompanyColumn)
        <div>
            <label class="form-label fw-bold">Company</label>
            <select id="filterCompany" class="form-select form-select-sm">
                <option value="">Any</option>
                @foreach($companiesForFilter as $company)
                <option value="{{ $company->id }}">{{ $company->company_name ?: $company->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="form-label fw-bold">Properties</label>
            <select id="filterHasProperties" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="1">Has properties</option>
                <option value="0">No properties</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Leads</label>
            <select id="filterHasLeads" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="1">Has leads</option>
                <option value="0">No leads</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Plan</label>
            <select id="filterPlan" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="none">No Plan</option>
                @foreach($plansForFilter as $plan)
                <option value="{{ $plan->id }}">{{ $plan->getTranslation('name') }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Admin Approval Status</label>
            <select id="filterStatus" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Pending Duration</label>
            <select id="filterPendingDays" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="3">Pending 3+ days</option>
                <option value="7">Pending 7+ days</option>
                <option value="14">Pending 14+ days</option>
                <option value="30">Pending 30+ days</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Payment Status</label>
            <select id="filterPaymentStatus" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Payment Month <span class="text-muted fw-normal">(checks who paid that month)</span></label>
            <input type="month" id="filterPaymentMonth" class="form-control form-control-sm">
        </div>
        <div class="row g-2">
            <div class="col-6">
                <label class="form-label fw-bold">Joined From</label>
                <input type="date" id="filterJoinedFrom" class="form-control form-control-sm">
            </div>
            <div class="col-6">
                <label class="form-label fw-bold">Joined To</label>
                <input type="date" id="filterJoinedTo" class="form-control form-control-sm">
            </div>
        </div>

        <div class="d-flex gap-2 mt-2">
            <button type="button" id="applyFiltersBtn" class="btn btn-brand-add flex-grow-1">Apply Filters</button>
            <button type="button" id="resetFiltersBtn" class="btn btn-outline-secondary">Reset</button>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content reject-modal-content">
            <div class="modal-body text-center p-4 pb-2">
                <div class="reject-modal-icon"><i class="fas fa-ban"></i></div>
                <h5 class="fw-bold mb-2">Reject this account?</h5>
                <p class="text-muted mb-3" style="font-size: 0.85rem;">
                    Optionally add a reason — it will be included in the email sent to them.
                </p>
                <textarea id="rejectReasonInput" class="form-control" rows="3" placeholder="Reason for rejecting (optional)"></textarea>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="rejectReasonConfirmBtn">Reject Account</button>
            </div>
        </div>
    </div>
</div>

@include('portal-accounts.partials.reset-password-modal')
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(function() {
        const baseAjaxUrl = "{{ route('cms.portal-accounts.index', array_filter(['type' => $type])) }}";
        const exportUrlBase = "{{ route('cms.portal-accounts.export', array_filter(['type' => $type])) }}";

        function currentFilters() {
            const filters = {};
            const map = {
                filterCompany: 'company_id',
                filterHasProperties: 'has_properties',
                filterHasLeads: 'has_leads',
                filterPlan: 'plan_id',
                filterStatus: 'status',
                filterPendingDays: 'pending_days',
                filterPaymentStatus: 'payment_status',
                filterPaymentMonth: 'payment_month',
                filterJoinedFrom: 'joined_from',
                filterJoinedTo: 'joined_to',
            };
            Object.entries(map).forEach(([elId, param]) => {
                const el = document.getElementById(elId);
                if (el && el.value) filters[param] = el.value;
            });
            return filters;
        }

        function buildUrl(base, extraParams) {
            const url = new URL(base, window.location.origin);
            Object.entries(extraParams).forEach(([k, v]) => url.searchParams.set(k, v));
            return url.toString();
        }

        // Makes an applied filter set visible on the page itself (badge count + a chip row with a
        // one-click Clear), instead of only being noticeable via a subtle color change on the button.
        const FILTER_LABELS = {
            company_id: 'Company',
            has_properties: 'Properties',
            has_leads: 'Leads',
            plan_id: 'Plan',
            status: 'Approval Status',
            pending_days: 'Pending Duration',
            payment_status: 'Payment Status',
            payment_month: 'Payment Month',
            joined_from: 'Joined From',
            joined_to: 'Joined To',
        };

        function renderActiveFiltersBar(filters) {
            const entries = Object.entries(filters);
            const badge = $('#filterCountBadge');
            const bar = $('#activeFiltersBar');
            const chips = $('#activeFilterChips');

            if (entries.length === 0) {
                badge.addClass('d-none');
                bar.addClass('d-none');
                chips.empty();
                return;
            }

            chips.empty();
            entries.forEach(([param, value]) => {
                const elId = filterParamMap[param];
                const el = elId ? document.getElementById(elId) : null;
                let displayValue = value;
                if (el && el.tagName === 'SELECT') {
                    const opt = Array.from(el.options).find(o => o.value === value);
                    if (opt) displayValue = opt.text;
                }
                const label = FILTER_LABELS[param] || param;
                const safeValue = $('<div>').text(displayValue).html();
                chips.append(`<span class="badge bg-white text-dark border fw-normal">${label}: ${safeValue}</span>`);
            });

            badge.removeClass('d-none').text(entries.length);
            bar.removeClass('d-none');
        }

        // Deep-link support — e.g. a "View in Agents & Companies" link from a Plan's
        // detail page arrives as ?plan_id=3, which we pre-apply as if the filter panel had been used.
        const filterParamMap = {
            company_id: 'filterCompany',
            has_properties: 'filterHasProperties',
            has_leads: 'filterHasLeads',
            plan_id: 'filterPlan',
            status: 'filterStatus',
            pending_days: 'filterPendingDays',
            payment_status: 'filterPaymentStatus',
            payment_month: 'filterPaymentMonth',
            joined_from: 'filterJoinedFrom',
            joined_to: 'filterJoinedTo',
        };
        const urlParams = new URLSearchParams(window.location.search);
        const initialFilters = {};
        Object.entries(filterParamMap).forEach(([param, elId]) => {
            const value = urlParams.get(param);
            const el = document.getElementById(elId);
            if (value && el) {
                el.value = value;
                initialFilters[param] = value;
            }
        });
        if (Object.keys(initialFilters).length > 0) {
            $('#openFiltersBtn').addClass('has-filters');
        }
        renderActiveFiltersBar(initialFilters);

        const table = $('.premium-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: buildUrl(baseAjaxUrl, initialFilters),
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'contact', name: 'contact', orderable: false},
                @if(!$type)
                {data: 'type', name: 'type'},
                @endif
                @if($showCompanyColumn)
                {data: 'company', name: 'company', orderable: false, searchable: false},
                @endif
                @if($showAgentsColumn)
                {data: 'agents_count', name: 'agents_count', orderable: false, searchable: false},
                @endif
                {data: 'properties_count', name: 'properties_count'},
                {data: 'leads_count', name: 'leads_count'},
                {data: 'plan', name: 'plan', orderable: false, searchable: false},
                {data: 'payment_status', name: 'payment_status', orderable: false, searchable: false},
                {data: 'status', name: 'status', orderable: false, searchable: false},
                {data: 'is_active', name: 'is_active', orderable: false, searchable: false, className: 'text-center'},
                {data: 'joined', name: 'joined', searchable: false},
                {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'},
            ],
            order: [[1, 'desc']],
            drawCallback: function () {
                $('#selectAllRows').prop('checked', false);
                updateBulkButtons();
            }
        });

        const base = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/portal-accounts/";

        function updateBulkButtons() {
            const count = $('.row-checkbox:checked').length;
            $('#bulkDeleteCount').text(count);
            $('#bulkDeleteBtn').prop('disabled', count === 0);
            $('#bulkApproveCount').text(count);
            $('#bulkApproveBtn').prop('disabled', count === 0);
        }

        $('#selectAllRows').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).is(':checked'));
            updateBulkButtons();
        });

        $(document).on('change', '.row-checkbox', updateBulkButtons);

        $('#bulkDeleteBtn').on('click', function () {
            const ids = $('.row-checkbox:checked').map(function () { return $(this).val(); }).get();
            if (!ids.length) return;
            if (!confirm('Delete ' + ids.length + ' selected account(s) permanently? Their properties and CRM leads will be removed too.')) return;
            $.post(base + 'bulk-delete', { _token: '{{ csrf_token() }}', ids: ids })
                .done(() => table.ajax.reload(null, false));
        });

        $('#bulkApproveBtn').on('click', function () {
            const ids = $('.row-checkbox:checked').map(function () { return $(this).val(); }).get();
            if (!ids.length) return;
            if (!confirm('Approve ' + ids.length + ' selected account(s)? Each will get an approval email.')) return;
            $.post(base + 'bulk-approve', { _token: '{{ csrf_token() }}', ids: ids })
                .done(() => table.ajax.reload(null, false));
        });

        // Inline edit toggling — shared by the Plan, Status and Payment columns
        $(document).on('click', '.edit-cell-btn', function () {
            const cell = $(this).closest('.editable-cell');
            cell.find('.cell-display').addClass('d-none');
            cell.find('.cell-edit').removeClass('d-none');
        });

        $(document).on('click', '.cancel-cell-btn', function () {
            const cell = $(this).closest('.editable-cell');
            cell.find('.cell-edit').addClass('d-none');
            cell.find('.cell-display').removeClass('d-none');
        });

        $(document).on('click', '.submit-plan-btn', function () {
            const cell = $(this).closest('.editable-cell');
            const id = cell.data('id');
            const planId = cell.find('.plan-select-inline').val();
            $.post(base + id + '/assign-plan', { _token: '{{ csrf_token() }}', plan_id: planId })
                .done(() => table.ajax.reload(null, false));
        });

        function submitStatus(id, status, reason) {
            return $.post(base + id + '/status', { _token: '{{ csrf_token() }}', status: status, reason: reason })
                .done(() => table.ajax.reload(null, false));
        }

        // Custom reject-reason modal — replaces the native browser prompt() popup
        const rejectModalEl = document.getElementById('rejectReasonModal');
        const rejectModal = rejectModalEl ? new bootstrap.Modal(rejectModalEl) : null;
        let pendingRejectId = null;

        $(document).on('click', '.submit-status-btn', function () {
            const cell = $(this).closest('.editable-cell');
            const id = cell.data('id');
            const status = cell.find('.status-select-inline').val();

            if (status === 'rejected') {
                pendingRejectId = id;
                document.getElementById('rejectReasonInput').value = '';
                rejectModal.show();
                return;
            }

            submitStatus(id, status, null);
        });

        $('#rejectReasonConfirmBtn').on('click', function () {
            if (!pendingRejectId) return;
            const reason = document.getElementById('rejectReasonInput').value;
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';

            submitStatus(pendingRejectId, 'rejected', reason)
                .always(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    rejectModal.hide();
                    pendingRejectId = null;
                });
        });

        $(document).on('click', '.submit-payment-btn', function () {
            const cell = $(this).closest('.editable-cell');
            const id = cell.data('id');
            const paymentStatus = cell.find('.payment-select-inline').val();
            $.post(base + id + '/payment-status', { _token: '{{ csrf_token() }}', payment_status: paymentStatus })
                .done(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.toggle-active', function () {
            const id = $(this).data('id');
            $.post(base + id + '/toggle-active', { _token: '{{ csrf_token() }}' })
                .fail(() => table.ajax.reload(null, false));
        });

        // Filters
        $('#applyFiltersBtn').on('click', function () {
            const filters = currentFilters();
            table.ajax.url(buildUrl(baseAjaxUrl, filters)).load();
            $('#openFiltersBtn').toggleClass('has-filters', Object.keys(filters).length > 0);
            renderActiveFiltersBar(filters);
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('filtersPanel')).hide();
        });

        $('#resetFiltersBtn').on('click', function () {
            document.querySelectorAll('#filtersPanel select, #filtersPanel input').forEach(el => el.value = '');
            table.ajax.url(baseAjaxUrl).load();
            $('#openFiltersBtn').removeClass('has-filters');
            renderActiveFiltersBar({});
        });

        // Same reset, reachable directly from the page without opening the filters panel first.
        $('#clearFiltersBtn').on('click', function () {
            $('#resetFiltersBtn').trigger('click');
        });

        $('#exportBtn').on('click', function (e) {
            e.preventDefault();
            window.location.href = buildUrl(exportUrlBase, currentFilters());
        });
    });
</script>
@endpush
