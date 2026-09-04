@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.careers.candidates.index') }}">Candidates</a></li>
    <li class="breadcrumb-item active" aria-current="page">Candidate Details</li>
@endsection

@section('content')
@php
    $fieldLabels = [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'country' => 'Country',
        'apply_for' => 'Apply For',
        'experience' => 'Experience',
        'designation' => 'Designation',
        'submitted_at' => 'Submitted',
        'state' => 'State',
        'additional_information' => 'Additional Information',
        'attachment' => 'Attachment',
        'privacy' => 'Privacy Accepted',
    ];
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Candidate Details</h5>
        <a href="{{ route('cms.careers.candidates.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="card-body p-4">
        <div class="row g-4">
            @foreach($detailColumns as $field)
                @php
                    $value = $candidate->{$field};
                    if ($field === 'submitted_at') {
                        $value = optional($candidate->submitted_at)->format('d M Y H:i') ?: '-';
                    }
                    if ($field === 'privacy') {
                        $value = $candidate->privacy ? 'Yes' : 'No';
                    }
                @endphp

                @if($field === 'attachment')
                    <div class="col-md-6">
                        <label class="form-label fw-bold">{{ $fieldLabels[$field] }}</label>
                        <div>
                            @if($candidate->attachment)
                                <a href="{{ asset('storage/' . $candidate->attachment) }}" target="_blank" class="btn btn-outline-primary btn-sm">Open Attachment</a>
                            @else
                                <div class="form-control bg-light">-</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="col-md-6">
                        <label class="form-label fw-bold">{{ $fieldLabels[$field] }}</label>
                        @if($field === 'additional_information')
                            <div class="border rounded p-3 bg-light-subtle">{{ filled($value) ? $value : '-' }}</div>
                        @else
                            <div class="form-control bg-light">{{ filled($value) ? $value : '-' }}</div>
                        @endif
                    </div>
                @endif
            @endforeach

            @foreach(config('cms-kit.database.careers.candidates.extra_fields', []) as $key => $field)
                @php $value = data_get($candidate->extra_fields, $key); @endphp
                <div class="col-md-6">
                    <label class="form-label fw-bold">{{ $field['label'] ?? ucfirst(str_replace('_', ' ', $key)) }}</label>
                    <div class="form-control bg-light">{{ filled($value) ? $value : '-' }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
