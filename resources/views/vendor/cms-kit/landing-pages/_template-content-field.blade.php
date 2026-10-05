{{-- "Blog design" content for one language — inside the Title card's language panel, so a single
     language switcher covers both title and content. --}}
<div class="template-only-field mt-4">
    <label class="form-label fw-bold">Content <span class="text-danger">*</span></label>
    <textarea name="translations[{{ $lang->code }}][content]" class="form-control tinymce-editor @error("translations.{$lang->code}.content") is-invalid @enderror" rows="12">{{ old("translations.{$lang->code}.content", $page?->translations[$lang->code]['content'] ?? '') }}</textarea>
    @error("translations.{$lang->code}.content")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
