{{-- Quality score breakdown (ListingQualityService::score) — card hover popover + Listing Performance panel. --}}
<div class="pq-breakdown">
    @foreach($quality['groups'] as $group)
    <div class="pq-group">
        @foreach($group as $check)
        <div class="pq-row" @if($check['tip']) title="{{ $check['tip'] }}" @endif>
            <span class="pq-label">{{ $check['label'] }}</span>
            <span class="pq-points {{ $check['points'] >= $check['max'] ? 'is-full' : ($check['points'] > 0 ? 'is-part' : '') }}">
                <i class="fas {{ $check['points'] >= $check['max'] ? 'fa-circle-check' : ($check['points'] > 0 ? 'fa-circle-half-stroke' : 'fa-circle-xmark') }}"></i>{{ $check['points'] }}/{{ $check['max'] }}
            </span>
        </div>
        @if($check['tip'])<div class="pq-tip">{{ $check['tip'] }}</div>@endif
        @endforeach
    </div>
    @endforeach
</div>
