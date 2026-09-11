@php
    $isEdit = isset($plan);
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
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

@if($showLanguageUi)
<ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="langTabs" role="tablist">
    @foreach($languages as $lang)
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $lang->code }}-content" type="button" role="tab">
            <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
        </button>
    </li>
    @endforeach
</ul>
@endif

<div class="tab-content mb-4 language-switcher-content">
    @foreach($languages as $lang)
    @php $t = $isEdit ? ($plan->translations[$lang->code] ?? []) : []; @endphp
    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel">
        <div class="row g-4">
            <div class="col-12">
                <label class="form-label fw-bold">Plan Name <span class="text-danger">*</span></label>
                <input type="text" name="translations[{{ $lang->code }}][name]" class="form-control @error("translations.{$lang->code}.name") is-invalid @enderror" value="{{ old("translations.{$lang->code}.name", $t['name'] ?? '') }}" placeholder="e.g. Free, Basic, Pro" required>
                @error("translations.{$lang->code}.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Description</label>
                <input type="text" name="translations[{{ $lang->code }}][description]" class="form-control" value="{{ old("translations.{$lang->code}.description", $t['description'] ?? '') }}" placeholder="Short one-line summary">
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Features</label>
                <textarea name="features[{{ $lang->code }}]" class="form-control" rows="5" placeholder="One feature per line, e.g.&#10;List up to 3 properties&#10;Basic CRM access&#10;Email support">{{ old("features.{$lang->code}", implode("\n", $t['features'] ?? [])) }}</textarea>
                <small class="text-muted">One feature per line — shown as a checklist on the pricing card.</small>
            </div>
        </div>
    </div>
    @endforeach
</div>

<hr>

<div class="row g-4">
    <div class="col-md-3">
        <label class="form-label fw-bold">Billing Cycle <span class="text-danger">*</span></label>
        @php $currentCycle = old('billing_cycle', $plan->billing_cycle ?? 'monthly'); @endphp
        <select name="billing_cycle" class="form-select @error('billing_cycle') is-invalid @enderror" required>
            <option value="free" {{ $currentCycle === 'free' ? 'selected' : '' }}>Free</option>
            <option value="monthly" {{ $currentCycle === 'monthly' ? 'selected' : '' }}>Monthly</option>
            <option value="yearly" {{ $currentCycle === 'yearly' ? 'selected' : '' }}>Yearly</option>
            <option value="one_time" {{ $currentCycle === 'one_time' ? 'selected' : '' }}>One-time</option>
        </select>
        @error('billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-bold">Price (AED) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $plan->price ?? 0) }}" required>
        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-bold">Property Limit</label>
        <input type="number" min="0" name="property_limit" class="form-control" value="{{ old('property_limit', $plan->property_limit ?? '') }}" placeholder="Leave blank for unlimited">
    </div>
    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number" name="order_index" class="form-control" value="{{ old('order_index', $plan->order_index ?? ($nextOrder ?? 1)) }}" min="1">
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-md-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_popular" id="isPopular" {{ old('is_popular', $plan->is_popular ?? false) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="isPopular">Mark as "Popular"</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="status" id="planStatus" {{ old('status', $plan->status ?? true) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="planStatus">Active</label>
        </div>
    </div>
</div>

@push('scripts')
<script>
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
</script>
@endpush
