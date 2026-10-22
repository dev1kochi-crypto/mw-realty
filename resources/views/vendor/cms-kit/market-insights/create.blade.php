@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.market-insights.index') }}">Market Insights</a></li>
    <li class="breadcrumb-item active">Add Insight</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Add Market Insight</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('cms.market-insights.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('cms-kit::market-insights._form')

            <div class="mt-4 border-top pt-4">
                <button type="submit" class="btn btn-primary px-5">Save Insight</button>
                <a href="{{ route('cms.market-insights.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
