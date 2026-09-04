@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Testimonials</li>
@endsection

@section('content')
@php
    $sectionRequired = config('cms-kit.database.testimonials.section.required', []);
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
@endphp

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Section Settings</h5>
        <form action="{{ route('cms.testimonials.update-section') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="alert alert-info py-2 mb-3">
                <i class="fas fa-info-circle me-1"></i> Please ensure all required fields are filled{{ $showLanguageUi ? ' across all language tabs' : '' }} before saving.
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
                    <div class="row g-3">
                        @if(config('cms-kit.database.testimonials.section.title'))
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Title{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }} {!! in_array('title', $sectionRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <input type="text" name="translations[{{ $lang->code }}][section_title]" class="form-control @error("translations.{$lang->code}.section_title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.section_title", $section->translations[$lang->code]['section_title'] ?? '') }}" {{ in_array('title', $sectionRequired) ? 'required' : '' }}>
                            @error("translations.{$lang->code}.section_title")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @if(config('cms-kit.database.testimonials.section.sub_heading_1'))
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Sub Heading 1 {!! in_array('sub_heading_1', $sectionRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <input type="text" name="translations[{{ $lang->code }}][section_sub_heading_1]" class="form-control @error("translations.{$lang->code}.section_sub_heading_1") is-invalid @enderror" value="{{ old("translations.{$lang->code}.section_sub_heading_1", $section->translations[$lang->code]['section_sub_heading_1'] ?? '') }}" {{ in_array('sub_heading_1', $sectionRequired) ? 'required' : '' }}>
                            @error("translations.{$lang->code}.section_sub_heading_1")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @if(config('cms-kit.database.testimonials.section.sub_heading_2'))
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Sub Heading 2 {!! in_array('sub_heading_2', $sectionRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <input type="text" name="translations[{{ $lang->code }}][section_sub_heading_2]" class="form-control @error("translations.{$lang->code}.section_sub_heading_2") is-invalid @enderror" value="{{ old("translations.{$lang->code}.section_sub_heading_2", $section->translations[$lang->code]['section_sub_heading_2'] ?? '') }}" {{ in_array('sub_heading_2', $sectionRequired) ? 'required' : '' }}>
                            @error("translations.{$lang->code}.section_sub_heading_2")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @if(config('cms-kit.database.testimonials.section.description'))
                        <div class="col-12 mt-3">
                            <label class="form-label fw-bold">Description{{ $showLanguageUi ? ' (' . strtoupper($lang->code) . ')' : '' }} {!! in_array('description', $sectionRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                            <textarea name="description[{{ $lang->code }}]" class="form-control tinymce-editor @error("description.{$lang->code}") is-invalid @enderror" rows="3">{{ old("description.{$lang->code}", $section->description[$lang->code] ?? '') }}</textarea>
                            @error("description.{$lang->code}")
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @include('cms-kit::partials.extra-fields-translatable', [
                            'configKey' => 'testimonials.section',
                            'lang' => $lang,
                            'existingTranslations' => $section->translations ?? [],
                        ])
                    </div>
                </div>
                @endforeach
            </div>

            <div class="row mt-3">
                <div class="col-md-2">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="status" id="sectionStatus" {{ ($section->status ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="sectionStatus">Status</label>
                    </div>
                </div>
                @if(config('cms-kit.database.testimonials.section.display_home'))
                <div class="col-md-3">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="display_home" id="displayHome" value="1" {{ old('display_home', data_get($section->extra_fields, 'display_home', false)) ? 'checked' : '' }}>
                        <label class="form-check-label" for="displayHome">Display Home</label>
                    </div>
                </div>
                @endif
            </div>

            <div class="mt-3">
                <div class="row">
                    @if(config('cms-kit.database.testimonials.section.section_image'))
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Section Image {!! in_array('section_image', $sectionRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                        <div class="alert alert-light border py-1 px-2 mb-2" style="font-size: 0.8rem;">
                            <i class="fas fa-info-circle text-primary me-1"></i> This image is used across all languages.
                        </div>
                        <input type="file" name="section_image" class="form-control mb-2" {{ in_array('section_image', $sectionRequired) && !$section->section_image ? 'required' : '' }}>
                        @if(config('cms-kit.database.testimonials.section.section_image_alt'))
                        <input type="text" name="section_image_alt" class="form-control" placeholder="Section Image Alt Text" value="{{ $section->section_image_alt }}">
                        @endif
                        <small class="text-muted d-block mt-1">Recommended: {{ config('cms-kit.images.testimonials.section_image.width') }}x{{ config('cms-kit.images.testimonials.section_image.height') }}px</small>
                        @if($section->section_image)
                            <img src="{{ asset('storage/'.$section->section_image) }}" class="mt-2" style="height: 50px;">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_section_image" id="remove_section_image" value="1" {{ old('remove_section_image') ? 'checked' : '' }}>
                                <label class="form-check-label" for="remove_section_image">Remove current section image</label>
                            </div>
                        @endif
                    </div>
                    @endif

                    @if(config('cms-kit.database.testimonials.section.banner'))
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Banner {!! in_array('banner', $sectionRequired) ? '<span class="text-danger">*</span>' : '' !!}</label>
                        <div class="alert alert-light border py-1 px-2 mb-2" style="font-size: 0.8rem;">
                            <i class="fas fa-info-circle text-primary me-1"></i> This image is used across all languages.
                        </div>
                        <input type="file" name="banner" class="form-control mb-2" {{ in_array('banner', $sectionRequired) && !$section->banner ? 'required' : '' }}>
                        @if(config('cms-kit.database.testimonials.section.banner_alt'))
                        <input type="text" name="banner_alt" class="form-control" placeholder="Banner Alt Text" value="{{ $section->banner_alt }}">
                        @endif
                        <small class="text-muted d-block mt-1">Recommended: {{ config('cms-kit.images.testimonials.banner.width') }}x{{ config('cms-kit.images.testimonials.banner.height') }}px</small>
                        @if($section->banner)
                            <img src="{{ asset('storage/'.$section->banner) }}" class="mt-2" style="height: 50px;">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_banner" id="remove_testimonial_banner" value="1" {{ old('remove_banner') ? 'checked' : '' }}>
                                <label class="form-check-label" for="remove_testimonial_banner">Remove current banner</label>
                            </div>
                        @endif
                    </div>
                    @endif
                </div>

                @include('cms-kit::partials.extra-fields-global', [
                    'configKey' => 'testimonials.section',
                    'existingValues' => $section->extra_fields ?? [],
                ])

                <button type="submit" class="btn btn-primary">Update Section</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">Testimonial Items</h5>
        <div class="d-flex gap-2">
            <div class="dropdown" id="bulkActions" style="display: none;">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Bulk Actions (<span id="selectedCount">0</span>)
                </button>
                <ul class="dropdown-menu">
                    <li><button class="dropdown-item" onclick="bulkAction('active')"><i class="fas fa-check-circle text-success me-2"></i> Mark Active</button></li>
                    <li><button class="dropdown-item" onclick="bulkAction('inactive')"><i class="fas fa-times-circle text-secondary me-2"></i> Mark Inactive</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item" onclick="bulkAction('delete')"><i class="fas fa-trash text-danger me-2"></i> Delete Selected</button></li>
                </ul>
            </div>
            <a href="{{ route('cms.testimonials.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Testimonial
            </a>
        </div>
    </div>
    <form id="bulkForm" action="{{ route('cms.testimonials.bulk-action') }}" method="POST">
        @csrf
        <input type="hidden" name="action" id="bulkActionInput">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table premium-table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAll">
                            </th>
                            <th>#</th>
                            <th>Image</th>
                            <th>Name (Default)</th>
                            <th>Content Preview</th>
                            @if(config('cms-kit.database.testimonials.items.rating')) <th>Rating</th> @endif
                            <th>Order</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </form>
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
            ajax: "{{ route('cms.testimonials.index') }}",
            columns: [
                {data: 'checkbox', name: 'checkbox', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'image', name: 'image', orderable: false, searchable: false},
                {data: 'name_info', name: 'name_info'},
                {data: 'content_preview', name: 'content_preview'},
                @if(config('cms-kit.database.testimonials.items.rating'))
                {data: 'rating', name: 'rating', orderable: true, searchable: false},
                @endif
                {data: 'order', name: 'order'},
                {data: 'status_toggle', name: 'status_toggle', className: 'text-center'},
                {data: 'actions', name: 'actions', orderable: false, searchable: false}
            ],
            order: [[1, 'asc']],
            drawCallback: function() {
                updateBulkVisibility();
            }
        });

        $(document).on('change', '.status-toggle', function() {
            const id = $(this).data('id');
            $.post(`{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/testimonials/${id}/toggle-status`, {
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

        $(document).on('change', '.reorder-input', function() {
            const id = $(this).data('id');
            const order = $(this).val();
            $.post("{{ route('cms.testimonials.reorder') }}", {
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
            $('#bulkActionInput').val(action);
            $('#bulkForm').submit();
        };

        document.addEventListener('invalid', function(e) {
            let invalidTabPane = e.target.closest('.tab-pane');
            if (invalidTabPane) {
                let tabId = invalidTabPane.id;
                let tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
                if (tabBtn && !tabBtn.classList.contains('active')) {
                    $(tabBtn).tab('show');
                    setTimeout(() => { e.target.focus(); }, 150);
                }
            }
        }, true);
    });
</script>
@endpush
