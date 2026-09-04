@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">FAQs</li>
@endsection

@section('content')
@php $showLanguageUi = config('cms-kit.common.modules.languages', true); @endphp
<!-- Section Settings -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <h5 class="card-title mb-4 fw-bold text-primary">
            <i class="fas fa-cog me-2"></i>FAQ Section Header Settings
        </h5>
        <form action="{{ route('cms.faqs.update-section') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                <i class="fas fa-info-circle text-primary me-2"></i> 
                <strong>Note:</strong> Please ensure all required fields <span class="text-danger">(*)</span> are filled across all language tabs.
            </div>

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="sectionTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="section-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#section-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content language-switcher-content" id="sectionTabsContent">
                @foreach($languages as $lang)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="section-{{ $lang->code }}" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Section Title{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }} <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ $section->translations[$lang->code]['title'] ?? '' }}" required>
                            @error("translations.{$lang->code}.title")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text mt-1 text-muted">Example: Frequently Asked Questions</div>
                        </div>
                        
                        <div class="col-12 mt-3">
                            <label class="form-label fw-bold">Section Description{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }}</label>
                            <textarea name="translations[{{ $lang->code }}][description]" class="form-control @error("translations.{$lang->code}.description") is-invalid @enderror" rows="2">{{ $section->translations[$lang->code]['description'] ?? '' }}</textarea>
                            @error("translations.{$lang->code}.description")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text mt-1 text-muted">Short introduction for the FAQ section.</div>
                        </div>

                        @include('cms-kit::partials.extra-fields-translatable', [
                            'configKey' => 'faqs.section',
                            'lang' => $lang,
                            'existingTranslations' => $section->translations ?? [],
                        ])
                    </div>
                </div>
                @endforeach
            </div>

            @include('cms-kit::partials.extra-fields-global', [
                'configKey' => 'faqs.section',
                'existingValues' => $section->extra_fields ?? [],
            ])

            <div class="mt-4 pt-4 border-top d-flex justify-content-between align-items-center">
                <div class="form-check form-switch py-0">
                    <input class="form-check-input h5 mb-0" type="checkbox" name="status" id="sectionStatus" {{ ($section->status ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold ms-2 mt-1" for="sectionStatus">Status</label>
                </div>
                <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm">
                    <i class="fas fa-save me-2"></i>Update Section Settings
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">FAQ Management</h5>
        <div class="d-flex gap-2">
            <div class="dropdown" id="bulkActions" style="display: none;">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Bulk Actions (<span id="selectedCount">0</span>)
                </button>
                <ul class="dropdown-menu">
                    <li><button class="dropdown-item" onclick="bulkAction('activate')"><i class="fas fa-check-circle text-success me-2"></i> Mark Active</button></li>
                    <li><button class="dropdown-item" onclick="bulkAction('deactivate')"><i class="fas fa-times-circle text-secondary me-2"></i> Mark Inactive</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item" onclick="bulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
                </ul>
            </div>
            <a href="{{ route('cms.faqs.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add FAQ
            </a>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table premium-table mb-0 w-100">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th>#</th>
                        <th>Question</th>
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

<form id="bulkForm" action="{{ route('cms.faqs.bulk-action') }}" method="POST">
    @csrf
    <input type="hidden" name="action" id="bulkActionInput">
    <input type="hidden" name="ids[]" id="bulkIdsInput">
</form>
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
            ajax: "{{ route('cms.faqs.index') }}",
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'question', name: 'question'},
                {data: 'order', name: 'order'},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[1, 'asc']],
            drawCallback: function() {
                updateBulkVisibility();
            }
        });

        // Status Toggle
        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            $.post(`{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/faqs/${id}/toggle-status`, {
                _token: '{{ csrf_token() }}'
            })
            .done(function(data) {
                if (!data.success) {
                    table.ajax.reload(null, false);
                    alert('Error updating status');
                }
            })
            .fail(function() {
                table.ajax.reload(null, false);
                alert('Request failed');
            });
        });

        // Reorder
        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.faqs.reorder') }}", {
                _token: '{{ csrf_token() }}',
                id: id,
                order_index: order
            })
            .done(function() {
                table.ajax.reload(null, false);
            })
            .fail(function() {
                table.ajax.reload(null, false);
                alert('Failed to update order');
            });
        });

        // Delete
        $(document).on('click', '.delete-item', function() {
            if (confirm('Are you sure you want to delete this FAQ?')) {
                const id = $(this).data('id');
                $.ajax({
                    url: `{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/faqs/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function() {
                        table.ajax.reload(null, false);
                    }
                });
            }
        });

        // Bulk Select
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

        window.bulkAction = function(action) {
            if (action === 'delete' && !confirm('Are you sure you want to delete selected items?')) {
                return;
            }
            
            const ids = $('.row-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            $.post("{{ route('cms.faqs.bulk-action') }}", {
                _token: '{{ csrf_token() }}',
                action: action,
                ids: ids
            })
            .done(function() {
                table.ajax.reload();
            });
        };
    });
</script>
@endpush
