@extends('portal.crm._layout')

@section('title', 'Import Leads')

@section('crm-content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">Import Leads</div>
    <a href="{{ route('portal.crm.leads.index') }}" class="portal-btn-ghost btn btn-sm">Back to Leads</a>
</div>

<div class="portal-card p-4" style="max-width: 640px;">
    <div class="alert alert-info" style="font-size: 0.88rem;">
        Please use the provided Excel import template. Leads can only be imported using the supported template format.
        <a href="{{ route('portal.crm.leads.import.template') }}" class="fw-semibold">Download the template</a> first, fill it in, then upload it below.
    </div>

    <form action="{{ route('portal.crm.leads.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-semibold">Excel file (.xlsx, .xls, .csv)</label>
            <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
            @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <button type="submit" class="btn btn-portal-primary">Upload &amp; Import</button>
    </form>
</div>
@endsection
