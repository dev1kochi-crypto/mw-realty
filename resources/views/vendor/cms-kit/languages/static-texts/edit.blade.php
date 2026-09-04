@extends('cms-kit::layouts.cms')

@php
    $localeUpper = strtoupper((string) $language->code);
@endphp

@section('title', 'Manage Static Text — ' . $language->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.languages.index') }}">Languages</a></li>
    <li class="breadcrumb-item active" aria-current="page">Static Text ({{ $localeUpper }})</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm">
                <ul class="mb-0">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card glass-card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-2">
                    <div class="flex-grow-1">
                        <h4 class="fw-bold text-primary mb-2">Manage Static Text — {{ $language->name }} ({{ $localeUpper }})</h4>
                        <p class="text-muted small mb-1">
                            @if($isMaster)
                                Master file (<strong>{{ strtoupper($masterCode) }}.json</strong>). Keys come from development — edit <strong>values</strong> here only.
                            @else
                                Missing keys are auto-filled from English. Keys ending with <code class="small">.alt</code> or camelCase <code class="small">…Alt</code> stay English-only (accessibility copy).
                            @endif
                        </p>
                        <p class="text-muted small mb-0"><code class="small">{{ $jsonFilePath }}</code></p>
                    </div>
                    <div class="flex-shrink-0">
                        <a href="{{ route('cms.languages.index') }}" class="btn btn-outline-secondary">Back to Languages</a>
                    </div>
                </div>
            </div>
        </div>

        @can('languages.edit')
            <form method="post" action="{{ route('cms.languages.translations.update', $language) }}" id="static-translations-form">
                @csrf
                @method('PUT')
                <div class="card glass-card border-0 shadow-sm">
                    <div class="card-body border-bottom bg-white py-3">
                        <label class="form-label small fw-bold text-muted mb-1">Search keys</label>
                        <input type="search" class="form-control form-control-sm" id="static-text-key-search" autocomplete="off" placeholder="Type key name (e.g. listing.heroTitle)" style="max-width: 28rem;">
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 220px;">Key</th>
                                        <th style="min-width: 240px;">Value ({{ $localeUpper }})</th>
                                        @if(!$isMaster)
                                            <th style="min-width: 220px;">English reference</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($flat as $k => $v)
                                        @php
                                            $refEn = $englishFlat[$k] ?? '';
                                            $altOnly = !$isMaster && ((bool) preg_match('/(^|\.)alt$/i', $k) || (bool) preg_match('/Alt$/', $k));
                                        @endphp
                                        <tr data-static-key-row data-key="{{ strtolower($k) }}">
                                            <td class="ps-4">
                                                <input type="hidden" name="entries[{{ $loop->index }}][key]" value="{{ $k }}">
                                                <span class="form-control-plaintext font-monospace small py-1 text-danger">{{ $k }}</span>
                                            </td>
                                            <td class="@if(!$isMaster) border-end @endif">
                                                @if($altOnly)
                                                    <textarea class="form-control form-control-sm bg-light" rows="2" readonly>{{ $refEn }}</textarea>
                                                    <input type="hidden" name="entries[{{ $loop->index }}][value]" value="{{ $refEn }}">
                                                @else
                                                    <textarea class="form-control form-control-sm" name="entries[{{ $loop->index }}][value]" rows="2">{{ $v }}</textarea>
                                                @endif
                                            </td>
                                            @if(!$isMaster)
                                                <td class="pe-4">
                                                    <span class="form-control-plaintext small py-1 text-muted">{{ $refEn }}</span>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                        <a href="{{ route('cms.languages.index') }}" class="btn btn-outline-secondary">Back to Languages</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Translations
                        </button>
                    </div>
                </div>
            </form>
        @else
            <div class="card glass-card border-0 shadow-sm">
                <div class="card-body border-bottom py-3">
                    <input type="search" class="form-control form-control-sm" id="static-text-key-search" autocomplete="off" placeholder="Search keys">
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Key</th>
                                    <th>Value ({{ $localeUpper }})</th>
                                    @if(!$isMaster)
                                        <th class="pe-4">English reference</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($flat as $k => $v)
                                    @php $refEn = $englishFlat[$k] ?? ''; @endphp
                                    <tr data-static-key-row data-key="{{ strtolower($k) }}">
                                        <td class="ps-4 font-monospace small text-danger">{{ $k }}</td>
                                        <td><pre class="small mb-0 text-wrap">{{ $v }}</pre></td>
                                        @if(!$isMaster)
                                            <td class="pe-4 text-muted small">{{ $refEn }}</td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isMaster ? 2 : 3 }}" class="text-center text-muted py-4">No keys in this file yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 py-3">
                    <a href="{{ route('cms.languages.index') }}" class="btn btn-outline-secondary">Back to Languages</a>
                </div>
            </div>
        @endcan
    </div>
</div>

@push('scripts')
<script>
(function() {
    var inputs = document.querySelectorAll('#static-text-key-search');
    inputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var q = (input.value || '').toLowerCase().trim();
            document.querySelectorAll('[data-static-key-row]').forEach(function(row) {
                var key = (row.getAttribute('data-key') || '');
                row.style.display = (!q || key.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    });
})();
</script>
@endpush
@endsection
