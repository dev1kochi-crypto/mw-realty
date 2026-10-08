@extends('portal.crm._layout')

@section('title', 'Import Leads')

@section('crm-content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">Import Leads</div>
    <a href="{{ route('portal.crm.leads.index') }}" class="portal-btn-ghost btn btn-sm">Back to Leads</a>
</div>

<div class="portal-card p-4" style="max-width: 640px;">
    @if(session('importError'))
    <div class="alert alert-danger" style="font-size: 0.88rem;">{{ session('importError') }}</div>
    @endif
    <div class="alert alert-info" style="font-size: 0.88rem;">
        <strong>Direct import:</strong> use the provided Excel import template, or a file exported from Leads, without renaming or removing columns —
        <a href="{{ route('portal.crm.leads.import.template') }}" class="fw-semibold">download the template</a>.<br>
        <strong>Facebook leads:</strong> upload the CSV downloaded from Meta Leads Center / Ads Manager as it is —
        <a href="{{ route('portal.crm.leads.import.facebook-sample') }}" class="fw-semibold">download a sample file</a>.
    </div>

    <form action="{{ route('portal.crm.leads.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-semibold">What are you importing?</label>
            <select name="import_type" class="form-select @error('import_type') is-invalid @enderror">
                <option value="{{ \App\Imports\LeadsImport::FORMAT_TEMPLATE }}" @selected(old('import_type') !== \App\Imports\LeadsImport::FORMAT_FACEBOOK)>Direct import (template / Leads export)</option>
                <option value="{{ \App\Imports\LeadsImport::FORMAT_FACEBOOK }}" @selected(old('import_type') === \App\Imports\LeadsImport::FORMAT_FACEBOOK)>Facebook leads (Meta CSV)</option>
            </select>
            @error('import_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">File (.xlsx, .xls, .csv)</label>
            <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv,.tsv,.txt" required>
            @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <button type="submit" class="btn btn-portal-primary">Upload &amp; Import</button>
    </form>
</div>
@endsection
