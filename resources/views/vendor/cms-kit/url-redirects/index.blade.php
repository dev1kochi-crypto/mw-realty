@extends('cms-kit::layouts.cms')

@section('title', 'URL redirects')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">URL redirects</li>
@endsection

@section('content')
<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <p class="text-muted small mb-0">
        Rules run as HTTP middleware on normal page requests (GET/HEAD). If hits stay at 0, clear config cache (<code>php artisan config:clear</code>) and ensure your app runs this package’s middleware (global registration is enabled by default).
        Multilingual URLs like <code>/en/about</code> need either the same path stored or <code>locale_prefixes</code> in config so <code>/about</code> rules match.
    </p>
    @can('url-redirects.edit')
        <a href="{{ route('cms.url-redirects.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add redirect</a>
    @endcan
</div>

<div class="card glass-card border-0 shadow-sm mb-4">
    <div class="card-body p-3 border-bottom">
        <form method="get" action="{{ route('cms.url-redirects.index') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-0">Search</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Path or destination…">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Old path</th>
                    <th>Destination</th>
                    <th class="text-center">Code</th>
                    <th class="text-center">Hits</th>
                    <th class="text-center">Last hit</th>
                    <th class="text-center">Source</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($redirects as $r)
                    <tr>
                        <td class="ps-4"><code class="small">{{ $r->old_path }}</code></td>
                        <td class="small">{{ $r->status_code === 410 ? '—' : ($r->new_url ?? '—') }}</td>
                        <td class="text-center"><span class="badge bg-secondary">{{ $r->status_code }}</span></td>
                        <td class="text-center">{{ number_format($r->hit_count) }}</td>
                        <td class="text-center small text-muted">{{ $r->last_hit_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-center small">{{ $r->source ?? '—' }}</td>
                        <td class="text-end pe-4">
                            @can('url-redirects.edit')
                                <a href="{{ route('cms.url-redirects.edit', $r) }}" class="btn btn-sm btn-light border"><i class="fas fa-edit text-primary"></i></a>
                                <form action="{{ route('cms.url-redirects.destroy', $r) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this redirect?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No redirects yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($redirects->hasPages())
        <div class="card-footer bg-white border-0">{{ $redirects->links() }}</div>
    @endif
</div>
@endsection
