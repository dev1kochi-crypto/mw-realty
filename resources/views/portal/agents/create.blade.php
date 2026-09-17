@extends('portal.layouts.app')

@section('title', 'Add Agent')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">Add Agent</div>
    <a href="{{ route('portal.agents.index') }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="portal-card p-4">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('portal.agents.store') }}" method="POST">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">WhatsApp Number</label>
                <input type="text" name="whatsapp_number" class="form-control" value="{{ old('whatsapp_number') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Years of Experience</label>
                <input type="number" min="0" max="80" name="years_of_experience" class="form-control" value="{{ old('years_of_experience') }}">
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label fw-bold d-block">Preferred Areas</label>
                <div class="agent-repeater" data-field="preferred_areas">
                    @php $areas = old('preferred_areas', ['']); @endphp
                    @foreach($areas as $area)
                    <div class="row g-2 mb-2 repeater-row">
                        <div class="col-10"><input type="text" name="preferred_areas[]" class="form-control" placeholder="e.g. Downtown Dubai" value="{{ $area }}"></div>
                        <div class="col-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="fas fa-trash"></i></button></div>
                    </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-portal-primary add-row rounded-pill px-3" data-target=".agent-repeater[data-field='preferred_areas']" data-name="preferred_areas[]">
                    <i class="fas fa-plus me-1"></i> Add Area
                </button>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold d-block">Features / Specialties</label>
                <div class="agent-repeater" data-field="features">
                    @php $features = old('features', ['']); @endphp
                    @foreach($features as $feature)
                    <div class="row g-2 mb-2 repeater-row">
                        <div class="col-10"><input type="text" name="features[]" class="form-control" placeholder="e.g. Off-Plan Specialist" value="{{ $feature }}"></div>
                        <div class="col-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="fas fa-trash"></i></button></div>
                    </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-portal-primary add-row rounded-pill px-3" data-target=".agent-repeater[data-field='features']" data-name="features[]">
                    <i class="fas fa-plus me-1"></i> Add Feature
                </button>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top">
            <button type="submit" class="btn btn-portal-primary"><i class="fas fa-save me-1"></i> Save Agent</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    const addBtn = e.target.closest('.add-row');
    if (addBtn) {
        const container = document.querySelector(addBtn.dataset.target);
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 repeater-row';
        row.innerHTML = `<div class="col-10"><input type="text" name="${addBtn.dataset.name}" class="form-control"></div><div class="col-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="fas fa-trash"></i></button></div>`;
        container.appendChild(row);
        return;
    }
    const removeBtn = e.target.closest('.remove-row');
    if (removeBtn) {
        const container = removeBtn.closest('.agent-repeater');
        const row = removeBtn.closest('.repeater-row');
        if (container.querySelectorAll('.repeater-row').length > 1) {
            row.remove();
        } else {
            row.querySelector('input').value = '';
        }
    }
});
</script>
@endpush
