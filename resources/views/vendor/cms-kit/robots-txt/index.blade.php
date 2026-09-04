@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Robots.txt / SEO</li>
@stop

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1 fw-bold text-primary">Robots.txt Management</h5>
                        <p class="mb-0 text-muted small">View and manually edit the public robots.txt file used by crawlers.</p>
                    </div>
                    <div>
                        @if($exists)
                            <span class="badge rounded-pill px-3 py-2 theme-status-badge-success">
                                <i class="fas fa-check-circle me-2"></i>robots.txt exists
                            </span>
                        @else
                            <span class="badge rounded-pill px-3 py-2 theme-status-badge-danger">
                                <i class="fas fa-times-circle me-2"></i>robots.txt is missing
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border-start border-primary border-4 py-3 mb-4 shadow-sm">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle text-primary fs-5 me-3 mt-1"></i>
                        <div>
                            <h6 class="mb-1 fw-bold text-primary">Manual File Editor</h6>
                            <p class="mb-0 text-muted small">Open the current file, make direct text changes, and save it back to the public directory.</p>
                        </div>
                    </div>
                </div>

                <div class="border rounded-4 p-4 h-100">
                    <div class="d-flex align-items-center mb-3">
                        <div class="theme-icon-chip me-3">
                            <i class="fas fa-pen"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold">Edit Robots.txt</h6>
                            <p class="mb-0 text-muted small">Useful for allow, disallow, crawl-delay, and sitemap directives.</p>
                        </div>
                    </div>
                    <a href="{{ route('cms.robots-txt.edit') }}" class="btn btn-primary w-100">
                        <i class="fas fa-edit me-2"></i>{{ $exists ? 'Edit Robots.txt' : 'Create Robots.txt' }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold">Quick Actions</h6>
            </div>
            <div class="card-body p-4">
                @if($exists)
                <a href="{{ url('robots.txt') }}" target="_blank" class="btn btn-light border w-100 mb-3">
                    <i class="fas fa-external-link-alt me-2"></i>View Robots.txt File
                </a>
                @endif

                <div class="small text-muted">
                    <p class="mb-3">Robots.txt changes are applied immediately after saving.</p>
                    <p class="mb-0">Keep sitemap references updated so search engines can find your generated sitemap.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@stop
