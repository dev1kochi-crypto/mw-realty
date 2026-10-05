{{--
    One managed dropdown on the property form (options from Master › Property Options or CMS filters).
    Params: field (filter key), label, [name] (input name, defaults to field), [required], [current], [placeholder].
    Option text follows the language tab picked above the description (lang-aware-select, see _form).
    A locked field (approved DLD permit, see PortalPropertyController::lockedFields) is shown disabled
    and its saved value is posted through a hidden input.
--}}
@php
    $inputName = $name ?? $field;
    $filter = $filterOptions[$field] ?? null;
    $current = (string) ($current ?? $val($inputName));
    $isLocked = in_array($field, $lockedFields ?? [], true) || in_array($inputName, $lockedFields ?? [], true);
    $optionLabel = function ($option, string $langCode) use ($field, $staticLabels, $staticSelectFields, $fallbackLang) {
        if (in_array($field, $staticSelectFields, true)) {
            // Super Admin's label (Master › Property Options) wins; the static-texts JSON is the fallback.
            return ($option->translations[$langCode]['label'] ?? null)
                ?: ($staticLabels[$langCode]["{$field}.{$option->value}"]
                ?? $staticLabels[$fallbackLang]["{$field}.{$option->value}"]
                ?? $option->getTranslation('label', $langCode));
        }
        return $option->getTranslation('label', $langCode) ?: $option->getTranslation('label', $fallbackLang);
    };
@endphp
<label class="form-label fw-semibold" for="field-{{ $inputName }}">
    {{ $label }} @if($required ?? false)<span class="text-danger">*</span>@endif
    @if($isLocked)<i class="fas fa-lock text-muted ms-1 small" title="Matches the approved DLD permit"></i>@endif
</label>
@if($filter && $filter->activeValues->count())
    <select id="field-{{ $inputName }}" @unless($isLocked) name="{{ $inputName }}" @endunless class="form-select lang-aware-select @error($inputName) is-invalid @enderror"
            data-placeholder="{{ $placeholder ?? 'Search ' . strtolower($label) }}" @disabled($isLocked)>
        <option value=""></option>
        @foreach($filter->activeValues as $option)
        <option value="{{ $option->value }}" @selected($current === (string) $option->value)
            @foreach($languages as $lang) data-label-{{ $lang->code }}="{{ $optionLabel($option, $lang->code) }}" @endforeach
        >{{ $optionLabel($option, $languages->first()->code ?? $fallbackLang) }}</option>
        @endforeach
    </select>
    @if($isLocked)<input type="hidden" name="{{ $inputName }}" value="{{ $current }}">@endif
@else
    <input type="text" id="field-{{ $inputName }}" name="{{ $inputName }}" class="form-control" value="{{ $current }}" placeholder="Free text (no options configured yet)" @readonly($isLocked)>
@endif
@error($inputName)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
