@php
    $isEdit = isset($coupon);
    $selectedPlans = array_map('intval', old('plan_ids', $coupon->plan_ids ?? []) ?? []);
    $type = old('discount_type', $coupon->discount_type ?? 'percent');
    $duration = old('duration', $coupon->duration ?? 'once');
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($isEdit && $coupon->redemptions_count > 0)
<div class="alert alert-info small">
    <i class="fas fa-info-circle me-1"></i> Used {{ $coupon->redemptions_count }} time(s). Changes apply to new redemptions and to future payments of accounts already using it.
</div>
@endif

<div class="row g-4">
    <div class="col-md-5">
        <label class="form-label fw-bold">Coupon Code <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="text" name="code" id="couponCode" class="form-control text-uppercase fw-bold @error('code') is-invalid @enderror" value="{{ old('code', $coupon->code ?? '') }}" placeholder="e.g. WELCOME20" maxlength="40" required style="letter-spacing: 0.05em;">
            <button type="button" class="btn btn-outline-secondary" id="generateCode"><i class="fas fa-magic me-1"></i>Generate</button>
        </div>
        <small class="text-muted">Letters, numbers, dashes and underscores. Not case-sensitive for users.</small>
    </div>
    <div class="col-md-7">
        <label class="form-label fw-bold">Description</label>
        <input type="text" name="description" class="form-control" value="{{ old('description', $coupon->description ?? '') }}" placeholder="Internal note, e.g. Launch offer for new agencies" maxlength="255">
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold">Discount Type <span class="text-danger">*</span></label>
        <select name="discount_type" id="discountType" class="form-select">
            <option value="percent" @selected($type === 'percent')>Percentage (%)</option>
            <option value="fixed" @selected($type === 'fixed')>Fixed amount (AED)</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-bold">Discount Value <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text" id="discountPrefix">{{ $type === 'percent' ? '%' : 'AED' }}</span>
            <input type="number" step="0.01" min="0.01" name="discount_value" class="form-control @error('discount_value') is-invalid @enderror" value="{{ old('discount_value', $coupon->discount_value ?? '') }}" required>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-bold">Applies For <span class="text-danger">*</span></label>
        <select name="duration" id="couponDuration" class="form-select">
            @foreach(\App\Models\Coupon::DURATIONS as $value => $label)
            <option value="{{ $value }}" @selected($duration === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 {{ $duration === 'repeating' ? '' : 'd-none' }}" id="durationMonthsWrap">
        <label class="form-label fw-bold">Number of Months <span class="text-danger">*</span></label>
        <input type="number" min="1" max="120" name="duration_months" class="form-control @error('duration_months') is-invalid @enderror" value="{{ old('duration_months', $coupon->duration_months ?? 3) }}">
    </div>

    <div class="col-12">
        <label class="form-label fw-bold">Valid for Plans</label>
        <div class="d-flex flex-wrap gap-3 p-3 bg-light rounded-3">
            @forelse($plans as $plan)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" id="plan{{ $plan->id }}" @checked(in_array($plan->id, $selectedPlans, true))>
                <label class="form-check-label" for="plan{{ $plan->id }}">{{ $plan->getTranslation('name') }} <span class="text-muted small">(AED {{ number_format($plan->price, 0) }})</span></label>
            </div>
            @empty
            <span class="text-muted small">No paid plans yet.</span>
            @endforelse
        </div>
        <small class="text-muted">Leave all unticked to allow every paid plan.</small>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">Starts</label>
        <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', isset($coupon) && $coupon->starts_at ? $coupon->starts_at->format('Y-m-d\TH:i') : '') }}">
        <small class="text-muted">Blank = immediately</small>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-bold">Expires</label>
        <input type="datetime-local" name="expires_at" class="form-control @error('expires_at') is-invalid @enderror" value="{{ old('expires_at', isset($coupon) && $coupon->expires_at ? $coupon->expires_at->format('Y-m-d\TH:i') : '') }}">
        <small class="text-muted">Blank = never</small>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-bold">Total Uses</label>
        <input type="number" min="1" name="max_uses" class="form-control" value="{{ old('max_uses', $coupon->max_uses ?? '') }}" placeholder="Unlimited">
    </div>
    <div class="col-md-3">
        <label class="form-label fw-bold">Uses per Account</label>
        <input type="number" min="1" name="max_uses_per_user" class="form-control" value="{{ old('max_uses_per_user', $isEdit ? $coupon->max_uses_per_user : 1) }}" placeholder="Unlimited">
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="status" id="couponStatus" @checked(old('status', $coupon->status ?? true))>
            <label class="form-check-label fw-bold" for="couponStatus">Active</label>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('discountType').addEventListener('change', function () {
        document.getElementById('discountPrefix').textContent = this.value === 'percent' ? '%' : 'AED';
    });
    document.getElementById('couponDuration').addEventListener('change', function () {
        document.getElementById('durationMonthsWrap').classList.toggle('d-none', this.value !== 'repeating');
    });
    document.getElementById('generateCode').addEventListener('click', function () {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = 'MW';
        for (let i = 0; i < 6; i++) code += chars[Math.floor(Math.random() * chars.length)];
        document.getElementById('couponCode').value = code;
    });
</script>
@endpush
