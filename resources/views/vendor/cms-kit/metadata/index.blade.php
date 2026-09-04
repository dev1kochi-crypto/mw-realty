@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Metadata / SEO</li>
@endsection

@section('content')
<div class="mb-4">
    <p class="text-muted small mb-0">Manage meta title, description, and Open Graph data for each page.</p>
</div>

<div class="card glass-card">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table premium-table mb-0" id="metadataTable">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Meta Title</th>
                        <th>Meta Description</th>
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

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(function() {
        $('#metadataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('cms.metadata.index') }}",
            columns: [
                {data: 'page', name: 'page_key'},
                {data: 'meta_title', name: 'meta_title', orderable: false},
                {data: 'meta_description', name: 'meta_description', orderable: false},
                {data: 'actions', name: 'actions', orderable: false, searchable: false}
            ],
            order: [[0, 'asc']]
        });
    });
</script>
@endpush
