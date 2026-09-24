@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Property Posting Steps</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $sectionConfig = config('cms-kit.database.post-property-steps.section', []);
    $sectionRequired = $sectionConfig['required'] ?? [];
    $imageConfig = config('cms-kit.images.post-property-steps.section_image', []);
@endphp
<div class="row">
    <div class="col-12">
        <!-- Section Settings -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-primary">"Post Your Property" Section Settings</h6>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('cms.post-property-steps.update-section') }}" method="POST" enctype="multipart/form-data" id="sectionSettingsForm">
                    @csrf

                    @if($errors->any())
                    <div class="alert alert-danger">
                        <strong>Please fix the following before saving</strong> â€” note that some of these may be on the Arabic tab above.
                        <ul class="mb-0 mt-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="alert alert-light border-start border-primary border-4 py-2 mb-4 shadow-sm" style="font-size: 0.9rem;">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong>Note:</strong> This controls the "Post Your Property in 3 Simple Steps" section on the home page. Required fields are marked with <span class="text-danger">*</span>.
                    </div>

                    @if($showLanguageUi)
                    <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="sectionLanguageTabs" role="tablist">
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
                            <div class="row g-4">
                                @if($sectionConfig['title_1'] ?? true)
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Title 1</label>
                                    <input type="text" name="translations[{{ $lang->code }}][title_1]" class="form-control @error("translations.{$lang->code}.title_1") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title_1", $section->translations[$lang->code]['title_1'] ?? '') }}" placeholder="e.g. Sell Faster">
                                    <div class="form-text mt-1 text-muted">Small eyebrow line shown above the Title on the home page.</div>
                                    @error("translations.{$lang->code}.title_1")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                                @if($sectionConfig['title'] ?? true)
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Title {!! in_array('title', $sectionRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                                    <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $section->translations[$lang->code]['title'] ?? '') }}" placeholder="Post Your property in 3 simple steps" {{ in_array('title', $sectionRequired) ? 'required' : '' }}>
                                    @error("translations.{$lang->code}.title")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                                @if($sectionConfig['description'] ?? true)
                                <div class="col-12">
                                    <label class="form-label fw-bold">Description</label>
                                    <textarea name="translations[{{ $lang->code }}][description]" class="form-control @error("translations.{$lang->code}.description") is-invalid @enderror" rows="3">{{ old("translations.{$lang->code}.description", $section->translations[$lang->code]['description'] ?? '') }}</textarea>
                                    @error("translations.{$lang->code}.description")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                                <div class="col-12">
                                    <label class="form-label fw-bold">Listing Page Title</label>
                                    <input type="text" name="translations[{{ $lang->code }}][listing_page_title]" class="form-control" value="{{ old("translations.{$lang->code}.listing_page_title", $section->translations[$lang->code]['listing_page_title'] ?? '') }}" placeholder="Title used on the property-posting listing page">
                                    <div class="form-text mt-1 text-muted">Used as the page title when this content is shown on its own listing/detail page, separate from the home section title above.</div>
                                </div>
                                @if($sectionConfig['button_text'] ?? true)
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Button Text</label>
                                    <input type="text" name="translations[{{ $lang->code }}][button_text]" class="form-control @error("translations.{$lang->code}.button_text") is-invalid @enderror" value="{{ old("translations.{$lang->code}.button_text", $section->translations[$lang->code]['button_text'] ?? '') }}" placeholder="e.g. Post Your Property">
                                    @error("translations.{$lang->code}.button_text")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif
                                @if($sectionConfig['button_url'] ?? true)
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Button URL</label>
                                    <input type="url" name="translations[{{ $lang->code }}][button_url]" class="form-control @error("translations.{$lang->code}.button_url") is-invalid @enderror" value="{{ old("translations.{$lang->code}.button_url", $section->translations[$lang->code]['button_url'] ?? '') }}" placeholder="https://...">
                                    @error("translations.{$lang->code}.button_url")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endif

                                @include('cms-kit::partials.extra-fields-translatable', [
                                    'configKey' => 'post-property-steps.section',
                                    'lang' => $lang,
                                    'existingTranslations' => $section->translations ?? [],
                                ])
                            </div>
                        </div>
                        @endforeach
                    </div>

                    @if($sectionConfig['image'] ?? true)
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label d-block">Section Image</label>
                            <small class="text-muted d-block mb-1">Recommended size: {{ $imageConfig['width'] ?? '' }}x{{ $imageConfig['height'] ?? '' }}px, Max: {{ $imageConfig['max_size'] ?? '' }}KB</small>
                            <input type="file" name="section_image" class="form-control">
                            <input type="text" name="section_image_alt" class="form-control mt-2" placeholder="Image ALT text" value="{{ old('section_image_alt', $section->section_image_alt ?? '') }}">
                            @if($section?->section_image)
                                <div class="mt-2">
                                    <img src="{{ media_url($section->section_image) }}" class="img-thumbnail" style="height: 80px;">
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="remove_section_image" id="removeSectionImage" value="1">
                                        <label class="form-check-label" for="removeSectionImage">Remove current image</label>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    @include('cms-kit::partials.extra-fields-global', [
                        'configKey' => 'post-property-steps.section',
                        'existingValues' => $section->extra_fields ?? [],
                    ])

                    @if($sectionConfig['status'] ?? true)
                    <div class="row g-4">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" id="sectionStatus" {{ old('status', $section->status ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="sectionStatus">Status</label>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="col-12 border-top pt-4 mt-4">
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save me-2"></i>Update Section Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Steps List -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0">Steps</h5>
                <div class="d-flex gap-2">
                    @if($cmsUser->can('post-property-steps.delete'))
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
                    @if($cmsUser->can('post-property-steps.create'))
                    <a href="{{ route('cms.post-property-steps.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Step
                    </a>
                    @endif
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
                                <th style="width: 40px;">#</th>
                                <th>Icon</th>
                                <th>Title</th>
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
            ajax: "{{ route('cms.post-property-steps.index') }}",
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'image', name: 'image', orderable: false, searchable: false},
                {data: 'title', name: 'title'},
                {data: 'order', name: 'order', className: 'text-center'},
                {data: 'status', name: 'status', className: 'text-center'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ],
            order: [[4, 'asc']],
            drawCallback: function() {
                updateBulkVisibility();
            }
        });

        $(document).on('change', '.toggle-status', function() {
            const id = $(this).data('id');
            const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/post-property-steps/" + id + "/toggle-status";
            $.post(url, { _token: '{{ csrf_token() }}' })
                .done(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.post-property-steps.reorder') }}", {
                _token: '{{ csrf_token() }}',
                id: id,
                order_index: order
            }).done(() => table.ajax.reload(null, false));
        });

        $(document).on('click', '.delete-item', function() {
            if (confirm('Are you sure you want to delete this step?')) {
                const id = $(this).data('id');
                const url = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/post-property-steps/" + id;
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: () => table.ajax.reload(null, false)
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
            if (action === 'delete' && !confirm('Are you sure you want to delete selected items?')) {
                return;
            }

            const ids = $('.row-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            $.post("{{ route('cms.post-property-steps.bulk-action') }}", {
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

        // Jumping to the tab holding a required/invalid field (client-side blocked submit, or
        // a server-side validation error after redirect) is now handled globally for every CMS
        // page in layouts/cms.blade.php â€” nothing page-specific needed here anymore.
    });
</script>
@endpush
