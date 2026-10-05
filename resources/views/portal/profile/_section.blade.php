{{--
    One profile section: a read-only list plus its edit form (saved by the section-form script in
    portal/profile — POST /portal/profile with section = $key; fields per section:
    PortalProfileController::SECTIONS). $done / $total: how many fields are filled (page computes it).

    $fields: [name, label, type, col?, value?, display?, options?, placeholder?, help?, rtl?, expiry?, note?]
      type: text | email | url | number | date | year | textarea | select | multiselect | phone | tel | brokerage | static
      "static" is shown but never edited; "display" overrides the read-only text; "expiry" adds a
      Valid / Expires soon / Expired badge to a date.
--}}
@php
    $shown = function (array $f) {
        if (array_key_exists('display', $f)) return $f['display'];
        $v = $f['value'] ?? null;
        return match (true) {
            $v instanceof \DateTimeInterface => $v->format('d M Y'),
            is_array($v) => implode(', ', $v),
            default => $v,
        };
    };
    $expiryBadge = function ($date) {
        if (!$date instanceof \DateTimeInterface) return null;
        $days = (int) today()->diffInDays(\Illuminate\Support\Carbon::parse($date), false);
        return match (true) {
            $days < 0 => ['is-bad', 'Expired'],
            $days <= 30 => ['is-warn', $days === 0 ? 'Expires today' : "Expires in {$days} day" . ($days === 1 ? '' : 's')],
            default => ['is-ok', 'Valid'],
        };
    };
    $pct = ($total ?? 0) ? (int) round(($done ?? 0) / $total * 100) : null;
@endphp
<div class="dash-card pf-card">
    <div class="pf-card-head">
        <div class="min-w-0">
            <h2 class="pf-card-title">{{ $title }}</h2>
            @if(!empty($hint))<p class="pf-card-hint">{{ $hint }}</p>@endif
        </div>
        <div class="pf-card-tools">
            @if($pct !== null)
            <div class="pf-progress" title="{{ $done }} of {{ $total }} filled in">
                <span>{{ $done }}/{{ $total }}</span>
                <span class="pf-progress-bar"><i style="width: {{ $pct }}%"></i></span>
            </div>
            @endif
            @unless($readonly ?? false)
            <button type="button" class="pf-edit-btn section-edit-toggle" data-section="{{ $key }}"><i class="fas fa-pen"></i> Edit</button>
            @endunless
        </div>
    </div>

    <div class="section-view" data-section="{{ $key }}">
        <dl class="pf-list">
            @foreach($fields as $f)
            @php
                $type = $f['type'] ?? 'text';
                $text = $shown($f);
                $wide = ($f['col'] ?? 4) >= 12 || $type === 'textarea';
                $badge = !empty($f['expiry']) ? $expiryBadge($f['value'] ?? null) : null;
            @endphp
            <div class="pf-item {{ $wide ? 'is-wide' : '' }}">
                <dt>{{ $f['label'] }}</dt>
                <dd @if($f['rtl'] ?? false) dir="rtl" @endif class="{{ $type === 'textarea' ? 'is-text' : '' }}">
                    @if(filled($text))
                        @if($type === 'multiselect')
                            @foreach((array) ($f['value'] ?? []) as $chip)<span class="pf-chip">{{ $chip }}</span>@endforeach
                        @elseif($type === 'url')
                            <a href="{{ $text }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://(www\.)?#', '', $text) }}</a>
                        @else
                            {{ $text }}
                        @endif
                        @if($badge)<span class="pf-badge {{ $badge[0] }}">{{ $badge[1] }}</span>@endif
                    @elseif($type === 'static' || ($readonly ?? false))
                        <span class="pf-empty">—</span>
                    @else
                        <button type="button" class="pf-add section-edit-toggle" data-section="{{ $key }}" data-focus="{{ $f['name'] }}">Not added <span><i class="fas fa-plus"></i> Add</span></button>
                    @endif
                    @if(!empty($f['note']))<div class="pf-note">{!! $f['note'] !!}</div>@endif
                </dd>
            </div>
            @endforeach
        </dl>
    </div>
    @unless($readonly ?? false)
    <form class="section-edit d-none section-form" data-section="{{ $key }}">
        <div class="section-form-error text-danger small d-none mb-2"></div>
        <div class="row g-3">
            @foreach($fields as $f)
            @if(($f['type'] ?? 'text') === 'static')
            {{-- Shown in the form too (read-only), so every row of the view is accounted for. --}}
            <div class="col-md-{{ $f['col'] ?? 4 }}">
                <label class="form-label">{{ $f['label'] }} <i class="fas fa-lock text-muted small ms-1"></i></label>
                <input type="text" class="form-control form-control-sm" value="{{ $shown($f) }}" disabled aria-readonly="true">
                <div class="form-text">
                    {{ $f['locked_help'] ?? 'Set by MW Realty — can\'t be changed here.' }}
                    @if(!empty($f['locked_action']))<button type="button" class="pf-link-btn ms-0" data-bs-toggle="modal" data-bs-target="{{ $f['locked_action'] }}">Change it</button>@endif
                </div>
            </div>
            @continue
            @endif
            @php $type = $f['type'] ?? 'text'; $v = $f['value'] ?? null; $id = 'pf-' . $key . '-' . $f['name']; @endphp
            <div class="col-md-{{ $f['col'] ?? 4 }}">
                <label class="form-label" for="{{ $id }}">{{ $f['label'] }}@if(!empty($f['label_hint'])) <span class="text-muted small">({{ $f['label_hint'] }})</span>@endif</label>
                @switch($type)
                    @case('textarea')
                        <textarea id="{{ $id }}" name="{{ $f['name'] }}" class="form-control form-control-sm" rows="4" maxlength="2000" @if($f['rtl'] ?? false) dir="rtl" @endif placeholder="{{ $f['placeholder'] ?? '' }}">{{ $v }}</textarea>
                        @break
                    @case('date')
                        <input type="date" id="{{ $id }}" name="{{ $f['name'] }}" class="form-control form-control-sm" value="{{ $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v }}">
                        @break
                    @case('year')
                        <select id="{{ $id }}" name="{{ $f['name'] }}" class="form-select form-select-sm">
                            <option value="">{{ $f['placeholder'] ?? 'Select year' }}</option>
                            @for($y = now()->year; $y >= 1960; $y--)
                            <option value="{{ $y }}" @selected((int) $v === $y)>{{ $y }}</option>
                            @endfor
                        </select>
                        @break
                    @case('select')
                        <select id="{{ $id }}" name="{{ $f['name'] }}" class="form-select form-select-sm">
                            <option value="">{{ $f['placeholder'] ?? 'Select' }}</option>
                            @foreach($f['options'] as $opt)
                            <option value="{{ $opt }}" @selected((string) $v === (string) $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                        @break
                    @case('multiselect')
                        {{-- Sent even when nothing is ticked, so clearing every choice saves. --}}
                        <input type="hidden" name="{{ $f['name'] }}_sent" value="1">
                        <div class="profile-chip-picks">
                            @foreach($f['options'] as $opt)
                            <label class="profile-chip-pick"><input type="checkbox" name="{{ $f['name'] }}[]" value="{{ $opt }}" @checked(in_array($opt, (array) $v, true))><span>{{ $opt }}</span></label>
                            @endforeach
                        </div>
                        @break
                    @case('phone')
                        @php([$phoneCode, $phoneNumber] = \App\Rules\PhoneNumber::split($v))
                        <input type="hidden" name="phone_country_code" value="{{ $phoneCode ?? '+971' }}">
                        <input type="tel" id="{{ $id }}" name="{{ $f['name'] }}" class="form-control form-control-sm" value="{{ $phoneNumber }}" maxlength="20" placeholder="50 123 4567" data-phone-input>
                        @break
                    @case('brokerage')
                        {{-- Just the brokerage named for RERA/KYC. Joining an agency (and its plan) needs the
                             agency's acceptance and admin approval — see My Agency. --}}
                        <div class="brokerage-combo">
                            <input type="text" name="affiliated_brokerage" id="brokerageInput" class="form-control form-control-sm" maxlength="255" autocomplete="off"
                                   value="{{ $v }}" placeholder="Search agencies on MW Realty, or type your brokerage name"
                                   role="combobox" aria-expanded="false" aria-controls="brokerageList" data-search-url="{{ route('portal.agency.search') }}">
                            <div class="brokerage-combo__list d-none" id="brokerageList" role="listbox"></div>
                        </div>
                        @break
                    @default
                        <input type="{{ $type === 'tel' ? 'tel' : $type }}" id="{{ $id }}" name="{{ $f['name'] }}" class="form-control form-control-sm" value="{{ $v }}" placeholder="{{ $f['placeholder'] ?? '' }}"
                               @if($type === 'number') min="{{ $f['min'] ?? 0 }}" max="{{ $f['max'] ?? '' }}" @endif>
                @endswitch
                @if(!empty($f['help']))<div class="form-text">{{ $f['help'] }}</div>@endif
            </div>
            @endforeach
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-sm btn-success">Save</button>
            <button type="button" class="btn btn-sm btn-outline-secondary section-edit-cancel">Cancel</button>
        </div>
    </form>
    @endunless
</div>
