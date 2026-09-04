@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Languages</li>
@endsection

@section('content')

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card glass-card h-100">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            <div class="d-flex align-items-center mb-4">
                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                    <i class="fas fa-plus ps-0" style="font-size: 0.8rem;"></i>
                </div>
                <h5 class="fw-bold mb-0">Add New Language</h5>
            </div>
            <div class="alert alert-light border-start border-primary border-4 py-2 px-3 mb-4 small text-muted lh-sm">
                <p class="mb-2 mb-lg-1">English stays as the permanent default language and cannot be deleted.</p>
                <p class="mb-0">
                    Static texts: use the <i class="fas fa-file-lines"></i> icon in Actions — it opens <strong>Manage static text</strong> for that language (URL ends with <code class="small">/languages/&lt;numeric-id&gt;/translations</code>). Configure <code class="small">vue_editor_url</code> with placeholders <code class="small">{id}</code> or <code class="small">{code}</code> to open your Vue app instead. Keys come from development; you edit values (English reference column on non-English locales).
                </p>
            </div>
            <form action="{{ route('cms.languages.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Language Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg @error('name') is-invalid @enderror" placeholder="e.g. Arabic" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Language Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-lg @error('code') is-invalid @enderror" placeholder="e.g. ar" value="{{ old('code') }}" required>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if(config('cms-kit.database.languages.items.flag', true))
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Flag image</label>
                        <input type="file" name="flag_image" class="form-control form-control-lg @error('flag_image') is-invalid @enderror" accept="image/*">
                        @error('flag_image')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-1">Max {{ config('cms-kit.images.languages.flag.max_size', 256) }} KB, up to {{ config('cms-kit.images.languages.flag.width', 64) }}×{{ config('cms-kit.images.languages.flag.height', 48) }} px.</small>
                    </div>
                    @endif
                    @if(config('cms-kit.database.languages.items.flag_alt', true))
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Flag ALT text</label>
                        <input type="text" name="flag_alt" class="form-control form-control-lg @error('flag_alt') is-invalid @enderror" placeholder="e.g. United Kingdom flag" value="{{ old('flag_alt') }}">
                        @error('flag_alt')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @endif
                    <button type="submit" class="btn btn-primary btn-premium w-100 py-3 shadow-sm">
                        <i class="fas fa-save me-2"></i> Save Language
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card glass-card h-100">
            <div class="card-body p-4">
                <div class="p-4 border-bottom">
                    <h5 class="fw-bold mb-0">Existing Languages</h5>
                </div>
                <div class="table-responsive pt-4">
                    <table class="table premium-table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Name</th>
                                <th class="text-center">Flag</th>
                                <th>Code</th>
                                <th class="text-center">Default</th>
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
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(function() {
        $('.premium-table').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: "{{ route('cms.languages.index') }}",
            columns: [
                {data: 'name', name: 'name', className: 'ps-4', width: '22%'},
                {data: 'flag_thumb', name: 'flag_image', orderable: false, searchable: false, className: 'text-center', width: '10%'},
                {data: 'code', name: 'code', render: function(data) {
                    return '<code class="text-primary fw-bold">' + data + '</code>';
                }, width: '12%'},
                {data: 'default_badge', name: 'is_default', className: 'text-center', width: '18%'},
                {data: 'status_badge', name: 'status', className: 'text-center', width: '18%'},
                {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end pe-4 text-nowrap', width: '20%'}
            ],
            order: [[0, 'asc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search..."
            }
        });

        // Handle dynamically added edit buttons if needed, or use a single dynamic edit modal
        $(document).on('click', '.edit-language', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const code = $(this).data('code');
            const isEnglish = String(code).toLowerCase() === 'en';
            const flagUrl = $(this).data('flagUrl');
            const flagAlt = $(this).data('flagAlt');
            
            const form = $('#dynamicEditModal form');
            let updateUrl = "{{ route('cms.languages.update', ':id') }}";
            form.attr('action', updateUrl.replace(':id', id));
            form.find('input[name="name"]').val(name);
            form.find('input[name="code"]').val(code).prop('readonly', isEnglish);
            form.find('input[name="flag_alt"]').val(flagAlt || '');
            form.find('input[name="flag_image"]').val('');
            $('#editLanguageCodeHelp').toggleClass('d-none', !isEnglish);
            const preview = $('#editFlagPreview');
            if (flagUrl) {
                preview.html('<img src="' + flagUrl + '" alt="" class="rounded border" style="height: 40px; width: auto;">');
            } else {
                preview.empty();
            }
            
            new bootstrap.Modal(document.getElementById('dynamicEditModal')).show();
        });
    });
</script>

<div class="modal fade" id="dynamicEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Language</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Language Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Language Code</label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" required>
                        <small id="editLanguageCodeHelp" class="text-muted d-none">English code is locked as <code>en</code>.</small>
                    </div>
                    @if(config('cms-kit.database.languages.items.flag', true))
                    <div class="mb-3">
                        <label class="form-label">Flag image</label>
                        <div id="editFlagPreview" class="mb-2"></div>
                        <input type="file" name="flag_image" class="form-control" accept="image/*">
                        <small class="text-muted">Leave empty to keep current image. Max {{ config('cms-kit.images.languages.flag.max_size', 256) }} KB, up to {{ config('cms-kit.images.languages.flag.width', 64) }}×{{ config('cms-kit.images.languages.flag.height', 48) }} px.</small>
                    </div>
                    @endif
                    @if(config('cms-kit.database.languages.items.flag_alt', true))
                    <div class="mb-3">
                        <label class="form-label">Flag ALT text</label>
                        <input type="text" name="flag_alt" class="form-control">
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush
@endsection
