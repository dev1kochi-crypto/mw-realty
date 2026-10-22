@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Currencies</li>
@endsection

@section('content')

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4">
    @if($cmsUser->can('currencies.create'))
    <div class="col-xl-4">
        <div class="card glass-card h-100">
            <div class="card-body">
                @if ($errors->any() && !old('_edit_id'))
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="d-flex align-items-center mb-4">
                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                        <i class="fas fa-plus ps-0" style="font-size: 0.8rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Add New Currency</h5>
                </div>
                <div class="alert alert-light border-start border-primary border-4 py-2 px-3 mb-4 small text-muted lh-sm">
                    <p class="mb-2">All listing prices are entered and stored in <strong>AED</strong>. Visitors can switch the currency in the website header; prices are then shown as <em>AED price × rate</em>.</p>
                    <p class="mb-0"><strong>Rate</strong> = how much of this currency <strong>1 AED</strong> buys (e.g. USD ≈ 0.2723). Keep the rates up to date.</p>
                </div>
                <form action="{{ route('cms.currencies.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Currency Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" maxlength="3" class="form-control form-control-lg text-uppercase @error('code') is-invalid @enderror" placeholder="e.g. USD" value="{{ old('_edit_id') ? '' : old('code') }}" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg @error('name') is-invalid @enderror" placeholder="e.g. US Dollar" value="{{ old('_edit_id') ? '' : old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Symbol</label>
                        <input type="text" name="symbol" maxlength="10" class="form-control form-control-lg @error('symbol') is-invalid @enderror" placeholder="e.g. $ (optional — the code is used if empty)" value="{{ old('_edit_id') ? '' : old('symbol') }}">
                        @error('symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Rate (per 1 AED) <span class="text-danger">*</span></label>
                        <input type="number" name="rate" step="any" min="0" class="form-control form-control-lg @error('rate') is-invalid @enderror" placeholder="e.g. 0.2723" value="{{ old('_edit_id') ? '' : old('rate') }}" required>
                        @error('rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-premium w-100 py-3 shadow-sm">
                        <i class="fas fa-save me-2"></i> Save Currency
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="{{ $cmsUser->can('currencies.create') ? 'col-xl-8' : 'col-12' }}">
        <div class="card glass-card h-100">
            <div class="card-body p-4">
                <div class="p-4 border-bottom">
                    <h5 class="fw-bold mb-0">Currencies</h5>
                    <small class="text-muted">The default currency is what visitors see first; they can switch in the header.</small>
                </div>
                <div class="table-responsive pt-4">
                    <table class="table premium-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Name</th>
                                <th>Symbol</th>
                                <th class="text-end">Rate (per 1 AED)</th>
                                <th class="text-end">1,000,000 AED =</th>
                                <th class="text-center">Default</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($currencies as $currency)
                            <tr>
                                <td class="ps-4"><code class="text-primary fw-bold">{{ $currency->code }}</code>@if($currency->isBase()) <span class="badge bg-light text-dark border ms-1">Base</span>@endif</td>
                                <td>{{ $currency->name }}</td>
                                <td>{{ $currency->symbol ?: '—' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($currency->rate, 6, '.', ','), '0'), '.') }}</td>
                                <td class="text-end text-muted small">{{ $currency->symbol ?: $currency->code }} {{ number_format(1000000 * $currency->rate) }}</td>
                                <td class="text-center">
                                    @if($currency->is_default)
                                        <span class="badge bg-primary">Default</span>
                                    @elseif($cmsUser->can('currencies.edit'))
                                        <form action="{{ route('cms.currencies.set-default', $currency->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Make default</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($cmsUser->can('currencies.edit') && !($currency->is_default && $currency->status))
                                        <form action="{{ route('cms.currencies.toggle-status', $currency->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="badge border-0 {{ $currency->status ? 'bg-success' : 'bg-secondary' }}" title="Click to {{ $currency->status ? 'deactivate' : 'activate' }}">{{ $currency->status ? 'Active' : 'Inactive' }}</button>
                                        </form>
                                    @else
                                        <span class="badge {{ $currency->status ? 'bg-success' : 'bg-secondary' }}">{{ $currency->status ? 'Active' : 'Inactive' }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    @if($cmsUser->can('currencies.edit'))
                                        <button type="button" class="btn btn-sm btn-light edit-currency" title="Edit"
                                            data-id="{{ $currency->id }}" data-code="{{ $currency->code }}" data-name="{{ $currency->name }}"
                                            data-symbol="{{ $currency->symbol }}" data-rate="{{ $currency->rate + 0 }}" data-base="{{ $currency->isBase() ? 1 : 0 }}">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    @endif
                                    @if($cmsUser->can('currencies.delete') && !$currency->isBase() && !$currency->is_default)
                                        <form action="{{ route('cms.currencies.destroy', $currency->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete {{ $currency->code }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No currencies yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($cmsUser->can('currencies.edit'))
<div class="modal fade" id="editCurrencyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="editCurrencyForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="_edit_id" id="editCurrencyId" value="{{ old('_edit_id') }}">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Currency</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if ($errors->any() && old('_edit_id'))
                    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Currency Code</label>
                    <input type="text" name="code" id="editCurrencyCode" maxlength="3" class="form-control text-uppercase" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Name</label>
                    <input type="text" name="name" id="editCurrencyName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Symbol</label>
                    <input type="text" name="symbol" id="editCurrencySymbol" maxlength="10" class="form-control">
                </div>
                <div class="mb-1">
                    <label class="form-label small fw-bold text-muted text-uppercase">Rate (per 1 AED)</label>
                    <input type="number" name="rate" id="editCurrencyRate" step="any" min="0" class="form-control" required>
                    <small class="text-muted" id="editCurrencyBaseNote" style="display:none;">AED is the base currency — its code and rate (1) are fixed.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('editCurrencyModal');
        if (!modalEl) return;
        var modal = new bootstrap.Modal(modalEl);
        var updateUrl = @json(route('cms.currencies.update', '__ID__'));

        function open(d) {
            var isBase = String(d.base) === '1';
            document.getElementById('editCurrencyForm').action = updateUrl.replace('__ID__', d.id);
            document.getElementById('editCurrencyId').value = d.id;
            document.getElementById('editCurrencyCode').value = d.code || '';
            document.getElementById('editCurrencyCode').readOnly = isBase;
            document.getElementById('editCurrencyName').value = d.name || '';
            document.getElementById('editCurrencySymbol').value = d.symbol || '';
            document.getElementById('editCurrencyRate').value = isBase ? 1 : (d.rate || '');
            document.getElementById('editCurrencyRate').readOnly = isBase;
            document.getElementById('editCurrencyBaseNote').style.display = isBase ? '' : 'none';
            modal.show();
        }

        document.querySelectorAll('.edit-currency').forEach(function (btn) {
            btn.addEventListener('click', function () { open(btn.dataset); });
        });

        // Re-open the modal after a failed update, with what was typed.
        @if ($errors->any() && old('_edit_id'))
            open({ id: @json(old('_edit_id')), code: @json(old('code')), name: @json(old('name')), symbol: @json(old('symbol')), rate: @json(old('rate')), base: @json(optional($currencies->firstWhere('id', (int) old('_edit_id')))->isBase() ? 1 : 0) });
        @endif
    });
</script>
@endpush
@endsection
