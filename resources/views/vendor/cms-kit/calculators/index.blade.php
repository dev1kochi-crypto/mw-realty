@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Calculators</li>
@endsection

@section('content')
@php
    $paths = ['mortgage' => '/mortgage-calculator', 'eligibility' => '/eligibility-calculator', 'rent-vs-buy' => '/rent-vs-buy-calculator', 'refinance' => '/refinance-calculator'];
    $icons = ['mortgage' => 'fa-calculator', 'eligibility' => 'fa-user-check', 'rent-vs-buy' => 'fa-scale-balanced', 'refinance' => 'fa-arrows-rotate'];
@endphp
<div class="card glass-card">
    <div class="card-body p-4">
        <div class="p-4 border-bottom">
            <h5 class="fw-bold mb-0">Calculators</h5>
            <small class="text-muted">The calculator pages on the website. For each one you can edit the page title and intro, the SEO title and description, the enquiry form text and the FAQs shown under the calculator, in every language.</small>
        </div>
        <div class="table-responsive pt-4">
            <table class="table premium-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Calculator</th>
                        <th>Page</th>
                        <th class="text-center">FAQs</th>
                        <th>Updated</th>
                        <th class="text-center">In menu</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($calculators as $calculator)
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-light text-primary flex-shrink-0" style="width: 38px; height: 38px;">
                                    <i class="fas {{ $icons[$calculator->key] ?? 'fa-calculator' }}"></i>
                                </span>
                                <div style="min-width: 0;">
                                    <div class="fw-bold">{{ $calculator->getTranslation('title', $lang) }}</div>
                                    <small class="text-muted d-block text-truncate" style="max-width: 420px;">{{ $calculator->getTranslation('heading', $lang) }}</small>
                                </div>
                            </div>
                        </td>
                        <td><a href="{{ url($paths[$calculator->key] ?? '/') }}" target="_blank" class="text-primary small">{{ $paths[$calculator->key] ?? '' }} <i class="fas fa-arrow-up-right-from-square ms-1"></i></a></td>
                        <td class="text-center">{{ $calculator->faqs_count }}</td>
                        <td class="text-muted small text-nowrap">{{ $calculator->updated_at?->format('d M Y') }}</td>
                        <td class="text-center"><span class="badge {{ $calculator->status ? 'bg-success' : 'bg-secondary' }}">{{ $calculator->status ? 'Shown' : 'Hidden' }}</span></td>
                        <td class="text-end pe-4 text-nowrap">
                            @if($cmsUser->can('calculators.edit'))
                                <a href="{{ route('cms.calculators.edit', $calculator->id) }}" class="btn btn-sm btn-light" title="Edit"><i class="fas fa-pen"></i></a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
