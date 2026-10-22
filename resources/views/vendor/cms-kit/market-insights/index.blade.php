@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Market Insights</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $fallback = config('app.fallback_locale', 'en');
@endphp
<div class="row">
    <div class="col-12">
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-primary">Market Insights Page Header</h6>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('cms.market-insights.update-section') }}" method="POST">
                    @csrf
                    <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong>Note:</strong> Controls the title and intro text at the top of the public Market Insights page.
                    </div>

                    @if($showLanguageUi)
                    <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" role="tablist">
                        @foreach($languages as $lang)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" data-bs-toggle="tab" data-bs-target="#section-{{ $lang->code }}" type="button" role="tab">
                                <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                            </button>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    <div class="tab-content mb-4 language-switcher-content">
                        @foreach($languages as $lang)
                        @php $t = $section->translations[$lang->code] ?? []; @endphp
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="section-{{ $lang->code }}" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Page Title {!! $lang->code === $fallback ? '<span class="text-danger">*</span>' : '' !!}</label>
                                    <input type="text" name="translations[{{ $lang->code }}][listing_title]" class="form-control @error("translations.{$lang->code}.listing_title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.listing_title", $t['listing_title'] ?? '') }}" {{ $lang->code === $fallback ? 'required' : '' }} placeholder="Market Insights">
                                    @error("translations.{$lang->code}.listing_title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">Large title in the page banner.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Intro Heading</label>
                                    <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control" value="{{ old("translations.{$lang->code}.title", $t['title'] ?? '') }}" placeholder="Research & Reports">
                                    <div class="form-text">Heading above the list of insights.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Intro Text</label>
                                    <textarea name="translations[{{ $lang->code }}][description]" class="form-control" rows="2">{{ old("translations.{$lang->code}.description", $t['description'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-save me-2"></i>Update Page Header</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0">Market Insights</h5>
                <div class="d-flex gap-2">
                    @if(auth('cms')->user()->can('market-insights.delete'))
                    <div class="dropdown" id="bulkActions" style="display: none;">
                        <button class="btn btn-outline-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
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
                    @if(auth('cms')->user()->can('market-insights.create'))
                    <a href="{{ route('cms.market-insights.create') }}" class="btn btn-primary btn-sm px-3">
                        <i class="fas fa-plus me-1"></i> Add Insight
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100" id="insightsTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th style="width: 70px;">Image</th>
                                <th style="min-width: 150px;">Title</th>
                                <th style="width: 135px;">Topic / Region</th>
                                <th style="width: 105px;">Date</th>
                                <th style="width: 85px;">Order</th>
                                <th style="width: 80px;" class="text-center">Featured</th>
                                <th style="width: 75px;" class="text-center">Status</th>
                                <th style="width: 95px;" class="text-end pe-4">Actions</th>
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
    $(function () {
        const base = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/market-insights/";
        const token = "{{ csrf_token() }}";

        const table = $('#insightsTable').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: "{{ route('cms.market-insights.index') }}",
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'image', name: 'image', orderable: false, searchable: false},
                {data: 'title', name: 'title'},
                {data: 'topic', name: 'topic'},
                {data: 'published_at', name: 'published_at'},
                {data: 'order', name: 'order'},
                {data: 'featured', name: 'is_featured', className: 'text-center', searchable: false},
                {data: 'status', name: 'status', className: 'text-center', searchable: false},
                {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end pe-4'}
            ],
            order: [[5, 'asc']],
            drawCallback: updateBulkButton
        });

        function updateBulkButton() {
            const count = $('.row-checkbox:checked').length;
            $('#selectedCount').text(count);
            $('#bulkActions').toggle(count > 0);
            $('#selectAll').prop('checked', count > 0 && count === $('.row-checkbox').length);
        }

        $('#selectAll').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkButton();
        });
        $(document).on('change', '.row-checkbox', updateBulkButton);

        window.bulkAction = function (action) {
            if (action === 'delete' && !confirm('Delete the selected insights?')) return;
            const ids = $('.row-checkbox:checked').map(function () { return $(this).val(); }).get();
            $.post("{{ route('cms.market-insights.bulk-action') }}", {ids, action, _token: token}, function () {
                table.ajax.reload(null, false);
                $('#selectAll').prop('checked', false);
                updateBulkButton();
            });
        };

        $(document).on('click', '.delete-item', function () {
            if (!confirm('Delete this insight?')) return;
            $.ajax({url: base + $(this).data('id'), type: 'DELETE', data: {_token: token}, success: () => table.ajax.reload(null, false)});
        });

        $(document).on('change', '.toggle-status', function () {
            $.post(base + $(this).data('id') + '/toggle-status', {_token: token}).fail(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.toggle-featured', function () {
            $.post(base + $(this).data('id') + '/toggle-featured', {_token: token}).fail(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.reorder-input', function () {
            $.post("{{ route('cms.market-insights.reorder') }}", {id: $(this).data('id'), order_index: $(this).val(), _token: token}, () => table.ajax.reload(null, false));
        });
    });
</script>
@endpush
