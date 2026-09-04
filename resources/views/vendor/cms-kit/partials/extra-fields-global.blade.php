@php
    /**
     * Required variables:
     * - $configKey (string): config path under cms-kit.database (e.g., "brands.items")
     * - $existingValues (array): existing values for these fields (optional)
     */
    $configKey = $configKey ?? null;
    $existingValues = $existingValues ?? [];

    $extraFields = [];
    if ($configKey) {
        $extraFields = config("cms-kit.database.{$configKey}.extra_fields", []);
    }

    $supportedInputTypes = ['text', 'number', 'email', 'date', 'time', 'datetime-local', 'url', 'tel', 'color', 'password', 'month', 'week'];
    $globalFields = collect($extraFields)
        ->filter(fn($field) => !($field['translatable'] ?? false));
@endphp

@if($globalFields->count())
<hr class="my-4">
<div class="mb-4">
    <h6 class="fw-bold mb-3">Additional Fields</h6>
    <div class="row g-3">
        @foreach($globalFields as $fieldName => $fieldConfig)
            @php
                $fieldValue = old("extra_fields.{$fieldName}", $existingValues[$fieldName] ?? '');
                $fieldType = $fieldConfig['type'] ?? 'text';
                $isReadonly = (bool) ($fieldConfig['readonly'] ?? false);
                $usesTinyMce = ($fieldConfig['editor'] ?? null) === 'tinymce';
                $textareaClasses = trim('form-control' . ($usesTinyMce ? ' tinymce-extra-field' : '') . ' ' . ($errors->has("extra_fields.{$fieldName}") ? 'is-invalid' : ''));
                $columnClass = $fieldConfig['column_class'] ?? ($usesTinyMce ? 'col-12' : 'col-md-6');
                $inputType = in_array($fieldType, $supportedInputTypes, true) ? $fieldType : 'text';
                $readonlyAttribute = $isReadonly ? 'readonly' : '';
                $inputAttributes = collect([
                    'min' => $fieldConfig['min'] ?? null,
                    'max' => $fieldConfig['max'] ?? null,
                    'step' => $fieldConfig['step'] ?? null,
                    'pattern' => $fieldConfig['pattern'] ?? null,
                    'accept' => $fieldConfig['accept'] ?? null,
                ])->filter(fn ($value) => $value !== null && $value !== '')->all();
            @endphp
            <div class="{{ $columnClass }}">
                <label class="form-label">{{ $fieldConfig['label'] ?? ucfirst(str_replace('_', ' ', $fieldName)) }} {!! ($fieldConfig['required'] ?? false) ? '<span class="text-danger">*</span>' : '' !!}</label>

                @if($fieldType === 'textarea')
                    <textarea name="extra_fields[{{ $fieldName }}]" class="{{ $textareaClasses }}" rows="{{ $usesTinyMce ? 6 : 3 }}" placeholder="{{ $fieldConfig['placeholder'] ?? '' }}" {{ $readonlyAttribute }} {{ ($fieldConfig['required'] ?? false) ? 'required' : '' }}>{{ $fieldValue }}</textarea>
                @elseif($fieldType === 'select')
                    <select name="extra_fields[{{ $fieldName }}]" class="form-select @error("extra_fields.{$fieldName}") is-invalid @enderror" {{ $isReadonly ? 'onclick=return false; onkeydown=return false;' : '' }} {{ ($fieldConfig['required'] ?? false) ? 'required' : '' }}>
                        <option value="">-- Select --</option>
                        @foreach($fieldConfig['options'] ?? [] as $optValue => $optLabel)
                            <option value="{{ $optValue }}" {{ $fieldValue == $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @elseif($fieldType === 'file')
                    <input type="file" name="extra_fields[{{ $fieldName }}]" class="form-control @error("extra_fields.{$fieldName}") is-invalid @enderror" {{ $isReadonly ? 'disabled' : '' }} {{ (!$isReadonly && ($fieldConfig['required'] ?? false)) ? 'required' : '' }}>
                    @if(!empty($existingValues[$fieldName]))
                        <small class="text-muted d-block mt-1">Current: {{ $existingValues[$fieldName] }}</small>
                    @endif
                @elseif($fieldType === 'checkbox')
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="extra_fields[{{ $fieldName }}]" value="0">
                        <input type="checkbox" name="extra_fields[{{ $fieldName }}]" value="1" class="form-check-input @error("extra_fields.{$fieldName}") is-invalid @enderror" id="extra-field-{{ $fieldName }}" {{ $fieldValue ? 'checked' : '' }} {{ $isReadonly ? 'onclick=return false;' : '' }} {{ ($fieldConfig['required'] ?? false) ? 'required' : '' }}>
                        <label class="form-check-label" for="extra-field-{{ $fieldName }}">{{ $fieldConfig['placeholder'] ?? 'Enable' }}</label>
                    </div>
                @else
                    <input type="{{ $inputType }}" name="extra_fields[{{ $fieldName }}]" class="form-control @error("extra_fields.{$fieldName}") is-invalid @enderror" value="{{ $fieldValue }}" placeholder="{{ $fieldConfig['placeholder'] ?? '' }}" {{ $readonlyAttribute }} @foreach($inputAttributes as $attribute => $attributeValue) {{ $attribute }}="{{ $attributeValue }}" @endforeach {{ ($fieldConfig['required'] ?? false) ? 'required' : '' }}>
                @endif

                @if(isset($fieldConfig['helpText']))
                    <small class="text-muted d-block mt-1">{{ $fieldConfig['helpText'] }}</small>
                @endif

                @error("extra_fields.{$fieldName}")
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>
</div>
@endif
