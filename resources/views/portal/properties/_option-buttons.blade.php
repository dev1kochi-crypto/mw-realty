{{--
    A short managed option list shown as big icon buttons (radio group) — Category, Offering type.
    Params: field (filter key = input name), label, [required], [icons] (option value => Font Awesome icon).
    Locked (verified / approved permit, see PortalPropertyController::lockedFields): buttons disabled,
    the saved value posted through a hidden input. Labels follow the language picked in Description.
--}}
@php
    $filter = $filterOptions[$field] ?? null;
    $current = (string) $val($field);
    $isLocked = in_array($field, $lockedFields ?? [], true);
    $btnLabel = fn ($option, string $lang) => ($option->translations[$lang]['label'] ?? null)
        ?: ($staticLabels[$lang]["{$field}.{$option->value}"] ?? $staticLabels[$fallbackLang]["{$field}.{$option->value}"] ?? $option->getTranslation('label', $lang));
@endphp
<label class="form-label fw-semibold d-block">{{ $label }} @if($required ?? false)<span class="text-danger">*</span>@endif
    @if($isLocked)<i class="fas fa-lock text-muted ms-1 small" title="Matches the permit"></i>@endif</label>
@if($filter && $filter->activeValues->count())
<div class="option-buttons" role="radiogroup" aria-label="{{ $label }}" data-option-buttons="{{ $field }}">
    @foreach($filter->activeValues as $option)
    <input type="radio" class="btn-check" id="opt-{{ $field }}-{{ $option->value }}" value="{{ $option->value }}"
           @unless($isLocked) name="{{ $field }}" @endunless @checked($current === (string) $option->value) @disabled($isLocked)>
    <label for="opt-{{ $field }}-{{ $option->value }}" class="option-button lang-aware-label"
           @foreach($languages as $lang) data-label-{{ $lang->code }}="{{ $btnLabel($option, $lang->code) }}" @endforeach>
        <i class="fas {{ ($icons ?? [])[$option->value] ?? 'fa-circle-dot' }}"></i>
        <span>{{ $btnLabel($option, $languages->first()->code ?? $fallbackLang) }}</span>
    </label>
    @endforeach
</div>
@if($isLocked)<input type="hidden" name="{{ $field }}" value="{{ $current }}">@endif
@else
<input type="text" name="{{ $field }}" class="form-control" value="{{ $current }}" placeholder="Free text (no options configured yet)" @readonly($isLocked)>
@endif
@error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
