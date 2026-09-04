@php
    $redirectModel = $redirect ?? null;
    $oldPathValue = old('old_path', $redirectModel?->old_path ?? request('old_path', ''));
@endphp

<div class="mb-3">
    <label class="form-label small fw-bold text-muted text-uppercase">Old path <span class="text-danger">*</span></label>
    <input type="text" name="old_path" class="form-control @error('old_path') is-invalid @enderror" required
           placeholder="/blog/old-slug"
           value="{{ $oldPathValue }}">
    @error('old_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <small class="text-muted">Path only (leading slash), no domain — e.g. <code>/products/old-item</code></small>
</div>

<div class="mb-3">
    <label class="form-label small fw-bold text-muted text-uppercase">HTTP status</label>
    <select name="status_code" class="form-select @error('status_code') is-invalid @enderror" required id="redirect-status-code">
        @foreach([301 => '301 Permanent', 302 => '302 Temporary', 303 => '303 See Other', 307 => '307 Temporary (keep method)', 308 => '308 Permanent (keep method)', 410 => '410 Gone'] as $code => $label)
            <option value="{{ $code }}" @selected((int) old('status_code', $redirectModel?->status_code ?? 301) === $code)>{{ $label }}</option>
        @endforeach
    </select>
    @error('status_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3" id="redirect-target-wrap">
    <label class="form-label small fw-bold text-muted text-uppercase">Destination URL / path</label>
    <input type="text" name="new_url" class="form-control @error('new_url') is-invalid @enderror"
           placeholder="/blog/new-slug or https://example.com/page"
           value="{{ old('new_url', $redirectModel?->new_url ?? '') }}">
    @error('new_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <small class="text-muted d-none" id="redirect-target-hint-gone">Not used for 410 Gone.</small>
</div>

<div class="mb-3">
    <label class="form-label small fw-bold text-muted text-uppercase">Notes</label>
    <textarea name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror" placeholder="Optional">{{ old('notes', $redirectModel?->notes ?? '') }}</textarea>
    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@php
    $activeDefault = ($redirectModel === null || $redirectModel->is_active) ? '1' : '0';
@endphp
<div class="form-check mb-3">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="redirect-active"
           @checked((string) old('is_active', $activeDefault) === '1')>
    <label class="form-check-label" for="redirect-active">Active</label>
</div>

@push('scripts')
<script>
(function() {
    var sel = document.getElementById('redirect-status-code');
    var wrap = document.getElementById('redirect-target-wrap');
    var hint = document.getElementById('redirect-target-hint-gone');
    function toggle() {
        var gone = sel && parseInt(sel.value, 10) === 410;
        if (wrap) wrap.style.opacity = gone ? '0.6' : '1';
        if (hint) hint.classList.toggle('d-none', !gone);
        var input = wrap ? wrap.querySelector('input[name="new_url"]') : null;
        if (input) input.required = !gone;
    }
    if (sel) {
        sel.addEventListener('change', toggle);
        toggle();
    }
})();
</script>
@endpush
