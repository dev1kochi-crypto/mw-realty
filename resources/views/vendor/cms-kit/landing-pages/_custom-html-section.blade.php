@php
    $nonFallbackLanguages = $languages->where('code', '!=', $fallbackLocale)->values();
@endphp

<div class="card bg-light border-0 mb-4 custom-only-field">
    <div class="card-body p-4">
        <ul class="nav nav-tabs mb-4" id="customHtmlTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-code-btn" data-bs-toggle="tab" data-bs-target="#tab-code" type="button" role="tab">
                    <i class="fas fa-code me-1"></i> Code
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-images-btn" data-bs-toggle="tab" data-bs-target="#tab-images" type="button" role="tab">
                    <i class="fas fa-images me-1"></i> Images
                </button>
            </li>
            @if($nonFallbackLanguages->count() > 0)
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-translations-btn" data-bs-toggle="tab" data-bs-target="#tab-translations" type="button" role="tab">
                    <i class="fas fa-language me-1"></i> Translations
                </button>
            </li>
            @endif
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-preview-btn" data-bs-toggle="tab" data-bs-target="#tab-preview" type="button" role="tab">
                    <i class="fas fa-eye me-1"></i> Preview
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- Code --}}
            <div class="tab-pane fade show active" id="tab-code" role="tabpanel">
                <ul class="text-muted mb-3" style="font-size: 0.82rem; padding-left: 1.1rem;">
                    <li>Write your page's HTML once here — images, layout, everything.</li>
                    <li><strong>Insert Image</strong>: nothing selected → adds a new image at your cursor. Text selected (e.g. an old image path) → replaces it with the new image's path.</li>
                    <li>One HTML works for every language — translate just the <em>text</em> in the Translations tab, and manage images in the Images tab.</li>
                </ul>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-bold mb-0">HTML <span class="text-danger custom-required-mark">*</span></label>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="insertImageBtn"><i class="fas fa-image me-1"></i> Insert Image</button>
                        <input type="file" id="contentImageInput" accept="image/*" class="d-none">
                    </div>
                </div>
                <textarea name="custom_html" id="customHtmlField" class="form-control {{ $htmlError ? 'is-invalid' : '' }}" rows="16" style="font-family: 'Courier New', monospace; font-size: 0.85rem;" placeholder="&lt;section&gt;...&lt;/section&gt;">{{ $htmlValue }}</textarea>
                @if($htmlError)<div class="invalid-feedback d-block">{{ $htmlError }}</div>@endif
                <small class="text-muted" id="imageUploadStatus"></small>

                <div class="mt-3">
                    <label class="form-label fw-bold">CSS (Optional)</label>
                    <textarea name="custom_css" id="customCssField" class="form-control" rows="6" style="font-family: 'Courier New', monospace; font-size: 0.85rem;" placeholder=".my-class { ... }">{{ $cssValue }}</textarea>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-bold">Script (Optional)</label>
                    <small class="text-muted d-block mb-1">Plain JavaScript — runs on the page after the HTML above (sliders, modals, custom init code, etc.). No <code>&lt;script&gt;</code> tags needed, just the code itself.</small>
                    <textarea name="custom_js" id="customJsField" class="form-control" rows="8" style="font-family: 'Courier New', monospace; font-size: 0.85rem;" placeholder="document.querySelector('...').addEventListener(...)">{{ $jsValue }}</textarea>
                </div>
            </div>

            {{-- Images --}}
            <div class="tab-pane fade" id="tab-images" role="tabpanel">
                <p class="text-muted small mb-3">Every image currently in your HTML, listed once. Click <strong>Replace</strong> on any row to upload a new file — it swaps that image everywhere it's used, automatically.</p>
                <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="detectImagesBtn"><i class="fas fa-sync-alt me-1"></i> Refresh List</button>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="imagesTable">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Preview</th>
                                <th>Path</th>
                                <th style="width: 160px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="imagesTableBody">
                            <tr><td colspan="3" class="text-muted text-center py-3">Open this tab to load the images currently in your HTML.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Translations --}}
            @if($nonFallbackLanguages->count() > 0)
            <div class="tab-pane fade" id="tab-translations" role="tabpanel">
                <p class="text-muted small mb-3">Every piece of text found in your HTML, listed once. Fill in the translation for each language — the layout and images don't change.</p>
                <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="detectTextBtn"><i class="fas fa-sync-alt me-1"></i> Refresh List</button>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="translationTable">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Original Text</th>
                                @foreach($nonFallbackLanguages as $lang)
                                <th>{{ $lang->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody id="translationTableBody">
                            <tr><td colspan="{{ $nonFallbackLanguages->count() + 1 }}" class="text-muted text-center py-3">Open this tab to load the text currently in your HTML.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Preview --}}
            <div class="tab-pane fade" id="tab-preview" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="text-muted small mb-0">Shows exactly what this page looks like right now, per language.</p>
                    @if($languages->count() > 1)
                    <select class="form-select form-select-sm w-auto" id="previewLangSelect">
                        @foreach($languages as $lang)
                        <option value="{{ $lang->code }}">{{ $lang->name }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
                <iframe id="previewFrame" style="width:100%; height:550px; border:1px solid #dee2e6; border-radius:8px; background:#fff;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    window.__landingPageExistingTranslations = @json($existingTranslations ?? []);
    window.__landingPageNonFallbackLangs = @json($nonFallbackLanguages->pluck('code'));
    window.__landingPageFallbackLocale = @json($fallbackLocale);
</script>
