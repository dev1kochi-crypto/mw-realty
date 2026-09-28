@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.market-insights.index') }}">Market Insights</a></li>
    <li class="breadcrumb-item active">Edit Insight</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Edit Market Insight</h5>
        @if($insight->status)
        <a href="{{ url('/market-insights/' . $insight->slug) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> View on site</a>
        @endif
    </div>
    <div class="card-body p-4">
        <form action="{{ route('cms.market-insights.update', $insight->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('cms-kit::market-insights._form')

            <div class="mt-4 border-top pt-4">
                <button type="submit" class="btn btn-primary px-5">Update Insight</button>
                <a href="{{ route('cms.market-insights.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
