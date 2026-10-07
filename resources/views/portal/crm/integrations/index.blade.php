@extends('portal.crm._layout')

@section('title', 'Integrations')

{{-- CRM › Integrations hub (IntegrationController::index): one card per integration with its status;
     each card opens that integration's own page. A new integration = a new entry in index(). --}}
@push('styles')
<style>
    .intg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; }
    .intg-tile { position: relative; display: flex; flex-direction: column; gap: 12px; padding: 20px; border: 1px solid var(--portal-border); border-radius: 16px; background: #fff; color: inherit; text-decoration: none; transition: border-color .15s, box-shadow .15s, transform .15s; }
    .intg-tile:hover { border-color: var(--tile-color); box-shadow: 0 12px 28px rgba(24, 39, 75, .09); transform: translateY(-2px); color: inherit; }
    .intg-tile:focus-visible { outline: 2px solid var(--tile-color); outline-offset: 2px; }
    .intg-tile__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .intg-tile__logo { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 14px; background: var(--tile-color); color: #fff; font-size: 1.35rem; box-shadow: 0 8px 18px color-mix(in srgb, var(--tile-color) 30%, transparent); }
    .intg-tile__name { font-weight: 700; color: var(--portal-primary-dark); margin: 0; }
    .intg-tile__cat { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--portal-muted); }
    .intg-tile__desc { flex: 1 1 auto; margin: 0; font-size: .84rem; color: var(--portal-muted); }
    .intg-tile__foot { display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--portal-border); font-size: .84rem; font-weight: 600; color: var(--tile-color); }
    .intg-tile__foot i { transition: transform .15s; }
    .intg-tile:hover .intg-tile__foot i { transform: translateX(3px); }
    .intg-tile--soon { align-items: center; justify-content: center; text-align: center; border-style: dashed; background: var(--portal-bg); color: var(--portal-muted); min-height: 220px; }
    .intg-tile--soon i { font-size: 1.4rem; margin-bottom: 4px; }
</style>
@endpush
@include('portal.crm.integrations._shared_styles')

@section('crm-content')
<div class="mb-3">
    <div class="portal-section-title mb-0">Integrations</div>
    <p class="text-muted mb-0" style="font-size: .85rem;">Connect the tools you already use — leads and listings flow into MW Realty automatically.</p>
</div>

<div class="intg-grid">
    @foreach($integrations as $integration)
    @php [$tone, $label] = $integration['status']; @endphp
    <a href="{{ $integration['url'] }}" class="intg-tile" style="--tile-color: {{ $integration['color'] }};" aria-label="{{ $integration['name'] }} — {{ $label }}">
        <div class="intg-tile__top">
            <span class="intg-tile__logo" aria-hidden="true"><i class="{{ $integration['icon'] }}"></i></span>
            <span class="intg-status intg-status--{{ $tone }}">@if($tone === 'ok')<i class="fas fa-circle-check"></i>@elseif($tone === 'warn')<i class="fas fa-hourglass-half"></i>@endif{{ $label }}</span>
        </div>
        <div>
            <div class="intg-tile__cat">{{ $integration['category'] }}</div>
            <h6 class="intg-tile__name">{{ $integration['name'] }}</h6>
        </div>
        <p class="intg-tile__desc">{{ $integration['description'] }}</p>
        <div class="intg-tile__foot">
            <span>{{ $tone === 'off' && !$isAdmin ? 'Connect' : 'Open' }}</span>
            <i class="fas fa-arrow-right"></i>
        </div>
    </a>
    @endforeach
    <div class="intg-tile intg-tile--soon">
        <i class="fas fa-puzzle-piece"></i>
        <div class="fw-semibold">More integrations coming</div>
        <div class="small">New integrations will appear here as they're added.</div>
    </div>
</div>
@endsection
