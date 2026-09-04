@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.testimonials.index') }}">Testimonials</a></li>
    <li class="breadcrumb-item active">Add Testimonial</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Add New Testimonial</h5>
    </div>
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form action="{{ route('cms.testimonials.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('cms-kit::testimonials._form', ['nextOrder' => $nextOrder])

            <div class="mt-4 border-top pt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i>Save Testimonial
                </button>
                <a href="{{ route('cms.testimonials.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
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
</script>
@endpush
