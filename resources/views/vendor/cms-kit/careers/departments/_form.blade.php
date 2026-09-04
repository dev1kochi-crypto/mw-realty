@php
    $departmentConfig = config('cms-kit.database.careers.departments', []);
    $item = $department ?? null;
    $isEdit = (bool) $item;
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
@endphp

<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">{{ $isEdit ? 'Edit Department' : 'Add Department' }}</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ $formAction }}" method="POST">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="departmentLanguageTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="department-tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#department-panel-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                    @php $translation = data_get($item, "translations.{$lang->code}", []); @endphp
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="department-panel-{{ $lang->code }}" role="tabpanel">
                        <div class="row g-4">
                            @if($departmentConfig['title'] ?? true)
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                                <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $translation['title'] ?? '') }}" required>
                                @error("translations.{$lang->code}.title")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                            @if($departmentConfig['description'] ?? true)
                            <div class="col-12">
                                <label class="form-label fw-bold">Description</label>
                                <textarea name="translations[{{ $lang->code }}][description]" class="form-control @error("translations.{$lang->code}.description") is-invalid @enderror" rows="4">{{ old("translations.{$lang->code}.description", $translation['description'] ?? '') }}</textarea>
                                @error("translations.{$lang->code}.description")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif

                            @include('cms-kit::partials.extra-fields-translatable', [
                                'configKey' => 'careers.departments',
                                'lang' => $lang,
                                'existingTranslations' => $item->translations ?? [],
                            ])
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4 align-items-end mb-4">
                @if($departmentConfig['stats'] ?? false)
                <div class="col-md-6">
                    <label class="form-label fw-bold">Stats</label>
                    <textarea name="stats_text" class="form-control @error('stats_text') is-invalid @enderror" rows="5" placeholder="One stat per line">{{ old('stats_text', implode("\n", $item->stats ?? [])) }}</textarea>
                    @error('stats_text')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                @endif
                @if($departmentConfig['order'] ?? true)
                <div class="col-md-4">
                    <label class="form-label fw-bold">Order</label>
                    <input type="number" name="order_index" class="form-control @error('order_index') is-invalid @enderror" value="{{ old('order_index', $item->order_index ?? ($nextOrder ?? 1)) }}" min="1">
                    @error('order_index')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endif
                @if($departmentConfig['status'] ?? true)
                <div class="col-md-4">
                    <div class="form-check form-switch mt-md-4 pt-md-2">
                        <input class="form-check-input" type="checkbox" name="status" id="departmentStatus" value="1" {{ old('status', $item->status ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="departmentStatus">Status (Active)</label>
                    </div>
                </div>
                @endif
            </div>

            @include('cms-kit::partials.extra-fields-global', [
                'configKey' => 'careers.departments',
                'existingValues' => $item->extra_fields ?? [],
            ])

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">{{ $submitLabel }}</button>
                <a href="{{ route('cms.careers.departments.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('invalid', function(e) {
        const invalidTabPane = e.target.closest('.tab-pane');
        if (invalidTabPane) {
            const tabId = invalidTabPane.id;
            const tabBtn = document.querySelector(`[data-bs-target="#${tabId}"]`);
            if (tabBtn && !tabBtn.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                setTimeout(() => { e.target.focus(); }, 150);
            }
        }
    }, true);
</script>
@endpush
