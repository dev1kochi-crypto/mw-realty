@extends('cms-kit::layouts.cms')

@section('title', '404 log')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">404 log</li>
@endsection

@section('content')
<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <p class="text-muted small mb-0">Aggregated missing paths after middleware runs (GET/HEAD only). Use to spot broken inbound links.</p>
    @can('url-miss-logs.edit')
        <form action="{{ route('cms.url-miss-logs.clear') }}" method="POST" class="d-inline" onsubmit="return confirm('Clear entire 404 log?');">
            @csrf
            <div class="form-check form-check-inline mb-0 me-2">
                <input class="form-check-input" type="checkbox" name="confirm_clear" value="1" id="confirm-clear-404">
                <label class="form-check-label small" for="confirm-clear-404">Confirm clear</label>
            </div>
            <button type="submit" class="btn btn-outline-danger btn-sm">Clear log</button>
        </form>
    @endcan
</div>

<div class="card glass-card border-0 shadow-sm mb-4">
    <div class="card-body p-3 border-bottom">
        <form method="get" action="{{ route('cms.url-miss-logs.index') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-0">Search path</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm">
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
                    <th class="ps-4">Path</th>
                    <th class="text-center">Hits</th>
                    <th class="text-center">First seen</th>
                    <th class="text-center">Last seen</th>
                    <th>Last referer</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($misses as $m)
                    <tr>
                        <td class="ps-4"><code class="small">{{ $m->path }}</code></td>
                        <td class="text-center">{{ number_format($m->hit_count) }}</td>
                        <td class="text-center small">{{ $m->first_seen_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="text-center small">{{ $m->last_seen_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="small text-muted text-break" style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($m->last_referer ?? '—', 120) }}</td>
                        <td class="text-end pe-4">
                            @can('url-miss-logs.edit')
                                @can('url-redirects.edit')
                                <a href="{{ route('cms.url-redirects.create', ['old_path' => $m->path]) }}"
                                   class="btn btn-sm btn-light border text-primary"
                                   title="Create redirect for this 404">
                                    <i class="fas fa-random"></i>
                                </a>
                                @endcan
                                <form action="{{ route('cms.url-miss-logs.destroy', $m) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this row?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger"><i class="fas fa-times"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No 404s logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($misses->hasPages())
        <div class="card-footer bg-white border-0">{{ $misses->links() }}</div>
    @endif
</div>
@endsection
