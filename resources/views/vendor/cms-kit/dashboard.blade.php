@extends('cms-kit::layouts.cms')

@section('title', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Overview</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Stat Cards -->
        @if(config('cms-kit.common.modules.banners', true) && $cmsUser->can('banners.view'))
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #4e73df !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Banners</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['banners'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-image fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(config('cms-kit.common.modules.faqs', true) && $cmsUser->can('faqs.view'))
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #1cc88a !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total FAQs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['faqs'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-question-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(config('cms-kit.common.modules.enquiries', true) && $cmsUser->can('enquiries.view'))
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #36b9cc !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Enquiries
                            </div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">{{ $stats['enquiries'] }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-envelope fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(config('cms-kit.common.modules.testimonials', true) && $cmsUser->can('testimonials.view'))
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #f6c23e !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Testimonials</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['testimonials'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comments fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(config('cms-kit.common.modules.careers', true) && $cmsUser->can('careers.view'))
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #e76f51 !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: #e76f51;">
                                Careers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['careers'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="row mt-4">
        <!-- Recent Enquiries -->
        @if(config('cms-kit.common.modules.enquiries', true) && $cmsUser->can('enquiries.view'))
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white border-bottom">
                    <h6 class="m-0 font-weight-bold text-gray-800">Recent Enquiries</h6>
                    <a href="{{ route('cms.enquiries.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Source</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentEnquiries as $enquiry)
                                <tr>
                                    <td>{{ $enquiry->name }}</td>
                                    <td>{{ $enquiry->email }}</td>
                                    <td><span class="badge bg-secondary">{{ $enquiry->page_source ?? 'N/A' }}</span></td>
                                    <td>{{ $enquiry->created_at->diffForHumans() }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No recent enquiries found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header py-3 bg-white border-bottom">
                    <h6 class="m-0 font-weight-bold text-gray-800">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @if(config('cms-kit.common.modules.banners', true) && $cmsUser->can('banners.edit'))
                        <a href="{{ route('cms.banners.create') }}" class="btn btn-primary text-start">
                            <i class="fas fa-plus-circle me-2"></i> Add New Banner
                        </a>
                        @endif
                        @if(config('cms-kit.common.modules.faqs', true) && $cmsUser->can('faqs.edit'))
                        <a href="{{ route('cms.faqs.create') }}" class="btn btn-success text-start">
                            <i class="fas fa-plus-circle me-2"></i> Add New FAQ
                        </a>
                        @endif
                        @if($cmsUser->can('site-information.view'))
                        <a href="{{ route('cms.site-information.index') }}" class="btn btn-info text-white text-start">
                            <i class="fas fa-cog me-2"></i> Site Settings
                        </a>
                        @endif
                        @if(config('cms-kit.common.modules.careers', true) && $cmsUser->can('careers.create'))
                        <a href="{{ route('cms.careers.create') }}" class="btn text-white text-start" style="background-color: #e76f51;">
                            <i class="fas fa-plus-circle me-2"></i> Add New Vacancy
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .text-xs { font-size: .7rem; }
    .text-gray-300 { color: #dddfeb !important; }
    .text-gray-800 { color: #5a5c69 !important; }
    .font-weight-bold { font-weight: 700 !important; }
    .card { transition: transform 0.2s ease-in-out; }
    .card:hover { transform: translateY(-5px); }
</style>
@endsection
