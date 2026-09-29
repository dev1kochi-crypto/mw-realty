{{-- Optional text laid over the ad image, per language. Leave empty for an image-only ad. Expects $languages, optional $ad. --}}
@php
    $adText = fn ($lang, $field) => old("translations.{$lang}.{$field}", $ad->translations[$lang][$field] ?? '');
@endphp
<hr class="my-4">
<div>
    <label class="form-label fw-bold mb-0">Text on the ad <span class="text-muted fw-normal">(optional)</span></label>
    <small class="text-muted d-block mb-3">Shown over the image — e.g. a small heading “MW Realty”, a title “Sell Your Property Faster” and one short line. Leave empty if the image already has its text. The whole ad links to the Link URL above; there's no separate button.</small>

    @if($languages->count() > 1)
    <ul class="nav nav-tabs mb-3" role="tablist">
        @foreach($languages as $language)
        <li class="nav-item" role="presentation">
            <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" data-bs-target="#adText-{{ $language->code }}" type="button" role="tab">{{ $language->name }}</button>
        </li>
        @endforeach
    </ul>
    @endif
    <div class="tab-content">
        @foreach($languages as $language)
        <div class="tab-pane fade @if($loop->first) show active @endif" id="adText-{{ $language->code }}" role="tabpanel" @if(in_array($language->code, ['ar', 'ur', 'fa'], true)) dir="rtl" @endif>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Small heading</label>
                    <input type="text" name="translations[{{ $language->code }}][eyebrow]" class="form-control" maxlength="60" value="{{ $adText($language->code, 'eyebrow') }}" placeholder="e.g. MW Realty">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Title</label>
                    <input type="text" name="translations[{{ $language->code }}][title]" class="form-control" maxlength="120" value="{{ $adText($language->code, 'title') }}" placeholder="e.g. Sell Your Property Faster">
                </div>
                <div class="col-12">
                    <label class="form-label">Short text</label>
                    <textarea name="translations[{{ $language->code }}][text]" class="form-control" rows="2" maxlength="300" placeholder="e.g. List with MW Realty and reach thousands of verified buyers across the UAE.">{{ $adText($language->code, 'text') }}</textarea>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
