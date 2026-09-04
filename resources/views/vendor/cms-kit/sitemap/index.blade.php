@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Sitemap / SEO</li>
@stop

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1 fw-bold text-primary">Sitemap Management</h5>
                        <p class="mb-0 text-muted small">Manage sitemap generation and keep search engines in sync with your latest content.</p>
                    </div>
                    <div>
                        @if($exists)
                            <span class="badge rounded-pill px-3 py-2 theme-status-badge-success">
                                <i class="fas fa-check-circle me-2"></i>sitemap.xml exists
                            </span>
                        @else
                            <span class="badge rounded-pill px-3 py-2 theme-status-badge-danger">
                                <i class="fas fa-times-circle me-2"></i>sitemap.xml is missing
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
                            <h6 class="mb-1 fw-bold text-primary">How It Works</h6>
                            <p class="mb-0 text-muted small">The sitemap is generated from your existing content modules. Use regeneration after major updates, or edit the XML manually when you need a custom entry.</p>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-4 p-4 h-100 bg-light-subtle">
                            <div class="d-flex align-items-center mb-3">
                                <div class="theme-icon-chip me-3">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">Automatic Generation</h6>
                                    <p class="mb-0 text-muted small">Best for refreshing the sitemap after adding or updating content.</p>
                                </div>
                            </div>
                            <form action="{{ route('cms.sitemap.generate') }}" method="GET">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-sync-alt me-2"></i>{{ $exists ? 'Regenerate Sitemap' : 'Generate Sitemap' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="border rounded-4 p-4 h-100">
                            <div class="d-flex align-items-center mb-3">
                                <div class="theme-icon-chip me-3">
                                    <i class="fas fa-pen"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">Manual Control</h6>
                                    <p class="mb-0 text-muted small">Open the XML editor when you need to fine-tune entries manually.</p>
                                </div>
                            </div>
                            @if($exists)
                                <a href="{{ route('cms.sitemap.edit') }}" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-edit me-2"></i>Edit Sitemap
                                </a>
                            @else
                                <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                    <i class="fas fa-edit me-2"></i>Edit Sitemap
                                </button>
                            @endif
                        </div>
                    </div>
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
                <a href="{{ url('sitemap.xml') }}" target="_blank" class="btn btn-light border w-100 mb-3">
                    <i class="fas fa-external-link-alt me-2"></i>View Sitemap File
                </a>
                @endif

                <div class="small text-muted">
                    <p class="mb-3">Use regeneration after content imports, major edits, or structural SEO changes.</p>
                    <p class="mb-0">Manual edits are useful for exceptional cases, but a full regeneration can overwrite those custom changes.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@stop
