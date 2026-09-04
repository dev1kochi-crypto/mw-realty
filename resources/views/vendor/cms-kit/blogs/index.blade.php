@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Blogs</li>
@endsection

@section('content')
@php $showLanguageUi = config('cms-kit.common.modules.languages', true); @endphp
<div class="row">
    <div class="col-12">
        <!-- Section Settings -->
        <!-- Section Settings -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-primary">Blog Section Header Settings</h6>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('cms.blogs.update-section') }}" method="POST">
                    @csrf
                    
                    <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                        <i class="fas fa-info-circle text-primary me-2"></i> 
                        <strong>Note:</strong> These settings control the header area of your Blogs page. Required fields are marked with <span class="text-danger">*</span>.
                    </div>

                    @if($showLanguageUi)
                    <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="blogSectionTabs" role="tablist">
                        @foreach($languages as $lang)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#content-{{ $lang->code }}" type="button" role="tab">
                                <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                            </button>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    <div class="tab-content mb-4 language-switcher-content" id="blogSectionContent">
                        @foreach($languages as $lang)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="content-{{ $lang->code }}" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Section Title <span class="text-danger">*</span></label>
                                    <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $section->translations[$lang->code]['title'] ?? '') }}" required>
                                    @error("translations.{$lang->code}.title")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text mt-1">Main heading for the blog section.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Listing Page Title</label>
                                    <input type="text" name="translations[{{ $lang->code }}][listing_title]" class="form-control @error("translations.{$lang->code}.listing_title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.listing_title", $section->translations[$lang->code]['listing_title'] ?? '') }}">
                                    @error("translations.{$lang->code}.listing_title")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text mt-1">Title displayed on the dedicated blog listing page.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Section Description</label>
                                    <textarea name="translations[{{ $lang->code }}][description]" class="form-control @error("translations.{$lang->code}.description") is-invalid @enderror" rows="3">{{ old("translations.{$lang->code}.description", $section->translations[$lang->code]['description'] ?? '') }}</textarea>
                                    @error("translations.{$lang->code}.description")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text mt-1">Brief introductory text for the blog section.</div>
                                </div>

                                @include('cms-kit::partials.extra-fields-translatable', [
                                    'configKey' => 'blogs.section',
                                    'lang' => $lang,
                                    'existingTranslations' => $section->translations ?? [],
                                ])
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="row g-4 align-items-center">
                        <div class="col-md-auto me-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" id="sectionStatus" {{ old('status', $section->status ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="sectionStatus">Status</label>
                            </div>
                        </div>
                        @if(config('cms-kit.database.blogs.section.display_home'))
                        <div class="col-md-auto">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="display_home" id="displayHome" value="1" {{ old('display_home', data_get($section->extra_fields, 'display_home', false)) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="displayHome">Display Home</label>
                            </div>
                        </div>
                        @endif
                    </div>
                       

                    @include('cms-kit::partials.extra-fields-global', [
                        'configKey' => 'blogs.section',
                        'existingValues' => $section->extra_fields ?? [],
                    ])

                    <div class="col-12 border-top pt-4">
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save me-2"></i>Update Section Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Blogs List -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0">Blogs List</h5>
                <div class="d-flex gap-2">
                    @if(auth('cms')->user()->can('blogs.delete'))
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
                    @if(auth('cms')->user()->can('blogs.create'))
                    <a href="{{ route('cms.blogs.create') }}" class="btn btn-primary btn-sm px-3">
                        <i class="fas fa-plus me-1"></i> Add Blog
                    </a>
                    @endif
                </div>
            </div>
            <form id="bulkForm" action="{{ route('cms.blogs.bulk-action') }}" method="POST">
                @csrf
                <input type="hidden" name="action" id="bulkActionInput">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table premium-table mb-0 w-100" id="blogsTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                <th style="width: 40px;">#</th>
                                <th style="width: 80px;">Image</th>
                                <th>Title</th>
                                <th style="width: 120px;">Date</th>
                                <th style="width: 100px;">Order</th>
                                <th style="width: 100px;" class="text-center">Status</th>
                                <th style="width: 100px;" class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            </form>
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
        const table = $('#blogsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('cms.blogs.index') }}",
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'image', name: 'image', orderable: false, searchable: false},
                {data: 'title', name: 'title'},
                {data: 'published_at', name: 'published_at'},
                {data: 'order', name: 'order'},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end pe-4'}
            ],
            order: [[1, 'asc']],
            drawCallback: function() {
                updateBulkButton();
            }
        });

        // Bulk Actions logic
        $('#selectAll').on('change', function() {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkButton();
        });

        $(document).on('change', '.row-checkbox', function() {
            updateBulkButton();
        });

        function updateBulkButton() {
            const count = $('.row-checkbox:checked').length;
            $('#selectedCount').text(count);
            $('#bulkActions').toggle(count > 0);
            $('#selectAll').prop('checked', count > 0 && count === $('.row-checkbox').length && $('.row-checkbox').length > 0);
        }

        window.bulkAction = function(action) {
            if (action === 'delete' && !confirm('Delete selected blogs?')) {
                return;
            }

            const ids = $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();
            $.post("{{ route('cms.blogs.bulk-action') }}", {
                ids: ids,
                action: action,
                _token: "{{ csrf_token() }}"
            }, function() {
                table.ajax.reload(null, false);
                $('#selectAll').prop('checked', false);
                updateBulkButton();
            });
        };

        $(document).on('click', '.delete-item', function() {
            const id = $(this).data('id');
            if (confirm('Delete this blog post?')) {
                $.ajax({
                    url: "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/blogs/" + id,
                    type: 'DELETE',
                    data: { _token: "{{ csrf_token() }}" },
                    success: function() {
                        table.ajax.reload(null, false);
                    }
                });
            }
        });

        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            $.post("{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/blogs/" + id + "/toggle-status", {
                _token: "{{ csrf_token() }}"
            }).fail(function() {
                table.ajax.reload(null, false);
            });
        });

        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.blogs.reorder') }}", {
                id: id,
                order_index: order,
                _token: "{{ csrf_token() }}"
            }, function() {
                table.ajax.reload(null, false);
            });
        });

        document.addEventListener('invalid', function(e) {
            let invalidTabPane = e.target.closest('.tab-pane');
            if (invalidTabPane) {
                let tabId = invalidTabPane.id;
                let tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
                if (tabBtn && !tabBtn.classList.contains('active')) {
                    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                    setTimeout(() => { e.target.focus(); }, 150);
                }
            }
        }, true);
    });
</script>
@endpush
