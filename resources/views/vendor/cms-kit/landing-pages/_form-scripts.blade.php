<script>
    let tinymceInitialized = false;

    function initTinyMce() {
        // No-op while the Template page type (the only one that used TinyMCE) is disabled and its
        // script tag is commented out — guards against a ReferenceError if it's ever loaded without
        // the .tinymce-editor field it targets, or vice versa.
        if (tinymceInitialized || typeof tinymce === 'undefined') return;
        tinymce.init({
            selector: '.tinymce-editor',
            height: 350,
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
            toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | removeformat | code | help',
        });
        tinymceInitialized = true;
    }

    function togglePageType() {
        // The Template-type radio is currently commented out of the form (Custom HTML only) — when it's
        // not in the DOM, default to "custom" so the fields below still show correctly.
        const customRadio = document.getElementById('typeCustom');
        const isCustom = customRadio ? customRadio.checked : true;
        document.querySelectorAll('.template-only-field').forEach(el => el.classList.toggle('d-none', isCustom));
        document.querySelectorAll('.custom-only-field').forEach(el => el.classList.toggle('d-none', !isCustom));
        const htmlField = document.getElementById('customHtmlField');
        if (htmlField) htmlField.required = isCustom;
        document.querySelectorAll('.custom-required-mark').forEach(el => el.classList.toggle('d-none', !isCustom));
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    // ---------- Slug auto-fill ----------
    // Mirrors the server's own Str::slug() fallback so the field isn't just an empty placeholder —
    // it fills in live from the fallback-language title, until the admin types a slug of their own.

    function slugify(text) {
        return (text || '').toString().trim().toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function initSlugAutoFill() {
        const slugField = document.querySelector('input[name="slug"]');
        const fallbackLocale = window.__landingPageFallbackLocale;
        if (!slugField || !fallbackLocale) return;

        const titleField = document.querySelector('input[name="translations[' + fallbackLocale + '][title]"]');
        if (!titleField) return;

        let slugEdited = slugField.value.trim() !== '';
        slugField.addEventListener('input', () => { slugEdited = true; });
        titleField.addEventListener('input', () => {
            if (!slugEdited) {
                slugField.value = slugify(titleField.value);
            }
        });
    }

    // ---------- Temp content-image tracking ----------
    // Every "Insert Image"/"Replace" upload lands in a temp folder first (see uploadContentImage()) and is
    // only promoted to permanent storage on Save. If the admin clicks "Cancel" instead, we tell the server
    // to delete these right away rather than waiting for the daily cleanup sweep.

    let uploadedTempFiles = [];

    function trackTempFile(url) {
        const marker = 'landing-pages/content/tmp/';
        const idx = url.indexOf(marker);
        if (idx !== -1) {
            uploadedTempFiles.push(url.slice(idx + marker.length));
        }
    }

    function discardTempImages() {
        if (!uploadedTempFiles.length) return;
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        uploadedTempFiles.forEach(f => formData.append('files[]', f));
        navigator.sendBeacon("{{ route('cms.landing-pages.discard-temp-images') }}", formData);
        uploadedTempFiles = [];
    }

    // ---------- Translations tab ----------

    function renderTranslationRows(strings) {
        const tbody = document.getElementById('translationTableBody');
        if (!tbody) return;

        const langs = window.__landingPageNonFallbackLangs || [];
        const existing = window.__landingPageExistingTranslations || {};

        if (!strings.length) {
            tbody.innerHTML = '<tr><td colspan="' + (langs.length + 1) + '" class="text-muted text-center py-3">No text found — add some to your HTML first.</td></tr>';
            return;
        }

        tbody.innerHTML = strings.map(function (str, idx) {
            const cells = langs.map(function (langCode) {
                const rows = existing[langCode] || [];
                const match = rows.find(r => r.original === str);
                const value = match ? match.translated : '';
                return '<td>'
                    + '<input type="hidden" name="text_translations[' + langCode + '][' + idx + '][original]" value="' + escapeHtml(str) + '">'
                    + '<input type="text" class="form-control form-control-sm" name="text_translations[' + langCode + '][' + idx + '][translated]" value="' + escapeHtml(value) + '">'
                    + '</td>';
            }).join('');
            return '<tr><td>' + escapeHtml(str) + '</td>' + cells + '</tr>';
        }).join('');
    }

    function detectText() {
        const html = document.getElementById('customHtmlField').value;
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('custom_html', html);

        fetch("{{ route('cms.landing-pages.extract-text') }}", { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => renderTranslationRows(data.strings || []));
    }

    function gatherRowsForLang(langCode) {
        const rows = [];
        document.querySelectorAll('#translationTableBody tr').forEach(function (tr) {
            const hidden = tr.querySelector('input[name^="text_translations[' + langCode + ']"][name$="[original]"]');
            const text = tr.querySelector('input[name^="text_translations[' + langCode + ']"][name$="[translated]"]');
            if (hidden) rows.push({ original: hidden.value, translated: text ? text.value : '' });
        });
        return rows;
    }

    // ---------- Images tab ----------

    function renderImageRows(sources) {
        const tbody = document.getElementById('imagesTableBody');
        if (!tbody) return;

        if (!sources.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">No images found — add some via "Insert Image" in the Code tab first.</td></tr>';
            return;
        }

        tbody.innerHTML = sources.map(function (src) {
            return '<tr data-src="' + escapeHtml(src) + '">'
                + '<td><img src="' + escapeHtml(src) + '" style="width:70px;height:50px;object-fit:cover;border-radius:4px;"></td>'
                + '<td><code style="font-size:0.75rem; word-break:break-all;">' + escapeHtml(src) + '</code></td>'
                + '<td>'
                +   '<button type="button" class="btn btn-sm btn-outline-primary replace-image-btn">Replace</button>'
                +   '<input type="file" class="d-none replace-image-input" accept="image/*">'
                +   '<div class="small text-muted replace-status mt-1"></div>'
                + '</td>'
                + '</tr>';
        }).join('');

        wireImageReplaceButtons();
    }

    function detectImages() {
        const html = document.getElementById('customHtmlField').value;
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('custom_html', html);

        fetch("{{ route('cms.landing-pages.extract-images') }}", { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => renderImageRows(data.images || []));
    }

    function wireImageReplaceButtons() {
        document.querySelectorAll('.replace-image-btn').forEach(function (btn) {
            const row = btn.closest('tr');
            const fileInput = row.querySelector('.replace-image-input');
            const statusEl = row.querySelector('.replace-status');

            btn.addEventListener('click', () => fileInput.click());

            fileInput.addEventListener('change', function () {
                const file = fileInput.files[0];
                if (!file) return;

                const oldSrc = row.dataset.src;
                statusEl.textContent = 'Uploading...';

                const formData = new FormData();
                formData.append('image', file);
                formData.append('_token', '{{ csrf_token() }}');

                fetch("{{ route('cms.landing-pages.upload-image') }}", { method: 'POST', body: formData })
                    .then(async res => {
                        if (!res.ok) {
                            const data = await res.json().catch(() => ({}));
                            throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : 'Upload failed.');
                        }
                        return res.json();
                    })
                    .then(data => {
                        trackTempFile(data.url);
                        const htmlField = document.getElementById('customHtmlField');
                        htmlField.value = htmlField.value.split(oldSrc).join(data.url);

                        row.dataset.src = data.url;
                        row.querySelector('img').src = data.url;
                        row.querySelector('code').textContent = data.url;
                        statusEl.textContent = 'Replaced everywhere it appeared.';
                    })
                    .catch(err => { statusEl.textContent = err.message; })
                    .finally(() => { fileInput.value = ''; });
            });
        });
    }

    // ---------- Preview tab ----------

    function runPreview() {
        const select = document.getElementById('previewLangSelect');
        const lang = select ? select.value : window.__landingPageFallbackLocale;
        const rows = (lang === window.__landingPageFallbackLocale) ? [] : gatherRowsForLang(lang);

        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('lang', lang);
        formData.append('custom_html', document.getElementById('customHtmlField').value);
        formData.append('custom_css', document.getElementById('customCssField').value);
        formData.append('custom_js', document.getElementById('customJsField').value);
        rows.forEach(function (r, i) {
            formData.append('rows[' + i + '][original]', r.original);
            formData.append('rows[' + i + '][translated]', r.translated);
        });

        fetch("{{ route('cms.landing-pages.preview') }}", { method: 'POST', body: formData })
            .then(res => res.text())
            .then(html => { document.getElementById('previewFrame').srcdoc = html; });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTinyMce();
        togglePageType();
        initSlugAutoFill();
        document.querySelectorAll('.page-type-radio').forEach(el => el.addEventListener('change', togglePageType));

        // Manual "refresh" buttons inside each tab.
        const detectTextBtn = document.getElementById('detectTextBtn');
        if (detectTextBtn) detectTextBtn.addEventListener('click', detectText);

        const detectImagesBtn = document.getElementById('detectImagesBtn');
        if (detectImagesBtn) detectImagesBtn.addEventListener('click', detectImages);

        // Auto-run each tab's scan/preview the moment it's opened, so nobody has to
        // remember to click "Refresh" first.
        const tabImagesBtn = document.getElementById('tab-images-btn');
        if (tabImagesBtn) tabImagesBtn.addEventListener('shown.bs.tab', detectImages);

        const tabTranslationsBtn = document.getElementById('tab-translations-btn');
        if (tabTranslationsBtn) tabTranslationsBtn.addEventListener('shown.bs.tab', detectText);

        const tabPreviewBtn = document.getElementById('tab-preview-btn');
        if (tabPreviewBtn) tabPreviewBtn.addEventListener('shown.bs.tab', runPreview);

        const previewLangSelect = document.getElementById('previewLangSelect');
        if (previewLangSelect) previewLangSelect.addEventListener('change', runPreview);

        // If this page already has detected text (editing an existing custom page), show it immediately.
        const existing = window.__landingPageExistingTranslations || {};
        const preloadedStrings = Object.values(existing).flat().map(r => r.original);
        if (preloadedStrings.length) {
            renderTranslationRows([...new Set(preloadedStrings)]);
        }

        // Insert Image (Code tab): nothing selected -> insert a full <img> tag at the cursor.
        // Text selected -> replace the selection with the bare uploaded URL (for swapping an existing src="...").
        const insertBtn = document.getElementById('insertImageBtn');
        if (insertBtn) {
            const fileInput = document.getElementById('contentImageInput');
            const htmlField = document.getElementById('customHtmlField');
            const statusEl = document.getElementById('imageUploadStatus');

            insertBtn.addEventListener('click', () => fileInput.click());

            fileInput.addEventListener('change', function () {
                const file = fileInput.files[0];
                if (!file) return;

                statusEl.textContent = 'Uploading...';
                const formData = new FormData();
                formData.append('image', file);
                formData.append('_token', '{{ csrf_token() }}');

                fetch("{{ route('cms.landing-pages.upload-image') }}", { method: 'POST', body: formData })
                    .then(async res => {
                        if (!res.ok) {
                            const data = await res.json().catch(() => ({}));
                            throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : 'Upload failed.');
                        }
                        return res.json();
                    })
                    .then(data => {
                        trackTempFile(data.url);
                        const hasSelection = htmlField.selectionStart !== htmlField.selectionEnd;
                        const snippet = hasSelection ? data.url : ('<img src="' + data.url + '" alt="">');
                        const start = htmlField.selectionStart ?? htmlField.value.length;
                        const end = htmlField.selectionEnd ?? htmlField.value.length;
                        htmlField.value = htmlField.value.slice(0, start) + snippet + htmlField.value.slice(end);
                        htmlField.focus();
                        htmlField.selectionStart = htmlField.selectionEnd = start + snippet.length;
                        statusEl.textContent = (hasSelection ? 'Selection replaced with: ' : 'Image tag inserted — path: ') + data.url;
                    })
                    .catch(err => { statusEl.textContent = err.message; })
                    .finally(() => { fileInput.value = ''; });
            });
        }

        document.querySelectorAll('.js-cancel-btn').forEach(btn => btn.addEventListener('click', discardTempImages));

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
    });
</script>
