@extends('cms-kit::layouts.cms')

@php
    $isEdit = $filter->exists;
    $fixed = $filter->isFixed();
    $fallback = config('app.fallback_locale');
    $current = [
        'page' => old('page', $filter->page),
        'field' => old('field', $filter->field),
        'type' => old('type', $filter->type ?: 'select'),
        'source' => old('options_source', $filter->options_source ?: 'auto'),
        'status' => (bool) old('status', $isEdit ? $filter->status : true),
    ];
    $translations = old('translations', $filter->translations ?? []);
    $settings = old('settings', $filter->settings ?? []);
    $options = old('options', $filter->relationLoaded('options') ? $filter->options->map(fn ($o) => [
        'value' => $o->value, 'translations' => $o->translations ?? [], 'status' => $o->status,
    ])->all() : []);
    $pageName = \App\Models\SearchFilter::PAGES[$current['page']] ?? 'Properties';
    $fieldLabel = $fields[$filter->field]['label'] ?? $filter->field;
    // Plain names for the "how it looks" choices (the model's names are admin jargon-free already).
    $typeText = [
        'select' => ['Dropdown', 'A list that opens — visitors pick one.'],
        'radio' => ['Buttons', 'A row of buttons — visitors tap one.'],
        'checkbox' => ['Tick boxes', 'Visitors can tick several.'],
        'text' => ['Text box', 'Visitors type a word.'],
        'number' => ['Number box', 'Visitors type one number.'],
        'range' => ['From – to', 'Two boxes: lowest and highest.'],
    ];
@endphp

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('crm.app', 'properties') }}">Properties</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cms.filters.index', ['page' => $filter->page]) }}">Search Filters</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $isEdit ? 'Edit ' . $filter->label() : 'Add filter' }}</li>
@endsection

@include('search-filters._styles')

@push('styles')
<style>
    .sfw [hidden] { display: none !important; }
    .sfw-intro { display: flex; gap: 1rem; align-items: center; padding: 1rem 1.25rem; border-radius: 16px; background: linear-gradient(135deg, rgba(4, 161, 204, .08), rgba(38, 67, 115, .06)); border: 1px solid rgba(4, 161, 204, .18); margin-bottom: 1.25rem; }
    .sfw-intro__icon { width: 46px; height: 46px; border-radius: 14px; background: #fff; color: var(--sf-teal); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(28, 35, 64, .08); }
    .sfw-intro strong { color: var(--sf-ink); }
    .sfw-intro p { margin: .15rem 0 0; font-size: .82rem; color: #4b5270; }

    .sfw-step { position: relative; background: #fff; border: 1px solid var(--sf-border); border-radius: 18px; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); padding: 1.35rem 1.5rem 1.5rem; margin-bottom: 1.25rem; }
    .sfw-step__head { display: flex; gap: .9rem; align-items: center; margin-bottom: 1rem; }
    .sfw-step__num { width: 36px; height: 36px; border-radius: 50%; background: var(--sf-navy); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .sfw-step__title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.08rem; color: var(--sf-ink); }
    .sfw-step__sub { font-size: .8rem; color: var(--sf-muted); }

    .sfw-row { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 1rem; align-items: start; }
    @media (max-width: 767.98px) { .sfw-row { grid-template-columns: 1fr; } }
    .sfw .form-select-lg, .sfw .form-control-lg { font-size: .95rem; border-radius: 12px; }

    .sfw-explain { display: flex; gap: .75rem; margin-top: .9rem; padding: .85rem 1rem; border-radius: 12px; background: var(--sf-soft); font-size: .85rem; color: #3d4460; }
    .sfw-explain i { color: var(--sf-teal); margin-top: .2rem; }
    .sfw-explain em { font-style: normal; color: var(--sf-muted); display: block; margin-top: .15rem; }

    /* Style cards with a small drawing of each control */
    .sfw-styles { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: .75rem; }
    .sfw-style { position: relative; border: 2px solid var(--sf-border); border-radius: 14px; padding: .85rem; cursor: pointer; background: #fff; transition: all .15s; margin: 0; }
    .sfw-style input { position: absolute; opacity: 0; pointer-events: none; }
    .sfw-style:hover { border-color: rgba(4, 161, 204, .45); }
    .sfw-style:has(input:checked) { border-color: var(--sf-teal); box-shadow: 0 0 0 4px rgba(4, 161, 204, .12); }
    .sfw-style:has(input:checked)::after { content: '\f00c'; font-family: 'Font Awesome 5 Free', 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 10px; right: 10px; width: 22px; height: 22px; border-radius: 50%; background: var(--sf-teal); color: #fff; font-size: .65rem; display: flex; align-items: center; justify-content: center; }
    .sfw-style strong { display: block; font-size: .9rem; color: var(--sf-ink); margin-top: .65rem; }
    .sfw-style span.d { font-size: .74rem; color: var(--sf-muted); }
    .sfw-mini { height: 54px; border-radius: 10px; background: var(--sf-soft); padding: .55rem; display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; overflow: hidden; }
    .sfw-mini i.box { flex: 1; height: 24px; border-radius: 6px; background: #fff; border: 1px solid #dfe2ea; display: flex; align-items: center; justify-content: flex-end; padding-right: .4rem; font-style: normal; font-size: .6rem; color: #9aa0b5; }
    .sfw-mini i.pill { height: 22px; padding: 0 .55rem; border-radius: 99px; background: #fff; border: 1px solid #dfe2ea; font-style: normal; font-size: .62rem; display: flex; align-items: center; color: #3d4460; }
    .sfw-mini i.pill.on { background: #1a1a2e; border-color: #1a1a2e; color: #fff; }
    .sfw-mini i.tick { font-style: normal; font-size: .62rem; color: #3d4460; display: flex; align-items: center; gap: .25rem; width: 46%; }
    .sfw-mini i.tick b { width: 11px; height: 11px; border-radius: 3px; border: 1.5px solid #9aa0b5; display: inline-block; }
    .sfw-mini i.tick b.on { background: var(--sf-teal); border-color: var(--sf-teal); }
    .sfw-mini i.dash { font-style: normal; color: var(--sf-muted); font-size: .7rem; }
    .sfw-one { font-size: .8rem; color: var(--sf-muted); margin-top: .6rem; }

    .sfw-source { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-bottom: 1rem; }
    @media (max-width: 767.98px) { .sfw-source { grid-template-columns: 1fr; } }
    .sfw-source label { position: relative; border: 2px solid var(--sf-border); border-radius: 14px; padding: 1rem 1rem 1rem 3.1rem; cursor: pointer; margin: 0; background: #fff; }
    .sfw-source label input { position: absolute; left: 1rem; top: 1.1rem; width: 18px; height: 18px; accent-color: var(--sf-teal); }
    .sfw-source label:has(input:checked) { border-color: var(--sf-teal); box-shadow: 0 0 0 4px rgba(4, 161, 204, .12); }
    .sfw-source strong { display: block; color: var(--sf-ink); font-size: .92rem; }
    .sfw-source label > span { font-size: .78rem; color: var(--sf-muted); }
    .sfw-badge { display: inline-block; margin-left: .35rem; padding: .05rem .5rem; border-radius: 99px; background: rgba(15, 157, 88, .1); color: #0f9d58; font-size: .66rem; font-weight: 800; vertical-align: middle; }

    .sfw-locked { display: flex; gap: 1rem; align-items: center; }
    .sfw-locked__icon { width: 46px; height: 46px; border-radius: 14px; background: var(--sf-soft); color: var(--sf-navy); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }

    /* Live preview, drawn like the website's filter panel */
    .sfw-preview { position: sticky; top: 90px; }
    .sfw-preview__card { background: #fff; border: 1px solid var(--sf-border); border-radius: 18px; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); overflow: hidden; margin-bottom: 1.25rem; }
    .sfw-preview__top { padding: .9rem 1.25rem; border-bottom: 1px solid var(--sf-border); display: flex; align-items: center; gap: .5rem; font-weight: 800; color: var(--sf-ink); font-size: .9rem; }
    .sfw-preview__top span { margin-left: auto; font-size: .7rem; font-weight: 700; color: var(--sf-muted); }
    .sfw-preview__site { padding: 1.25rem; background: #fbfbfd; }
    .sfw-preview__panel { background: #fff; border-radius: 14px; padding: 1.1rem; box-shadow: 0 8px 24px rgba(20, 20, 21, .08); }
    .sfw-preview__foot { padding: .75rem 1.25rem; font-size: .74rem; color: var(--sf-muted); border-top: 1px dashed var(--sf-border); }
    .sfw-preview__foot code { color: var(--sf-navy); background: var(--sf-soft); padding: .1rem .4rem; border-radius: 6px; word-break: break-all; }
    .sfw-save { background: #fff; border: 1px solid var(--sf-border); border-radius: 18px; padding: 1.1rem 1.25rem; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); }
    .sfw-save .sf-btn--primary { width: 100%; justify-content: center; padding: .75rem 1rem; font-size: .9rem; }
</style>
@endpush

@section('content')
<form class="sf sfw" method="POST" action="{{ $isEdit ? route('cms.filters.update', $filter) : route('cms.filters.store') }}" id="filterForm">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="sf-head">
        <div>
            <h4>{{ $isEdit ? 'Edit filter' : 'Add a filter' }}</h4>
            <p>{{ $fixed ? 'Home search · ' . $fieldLabel : 'Show it on the ' . $pageName . ' page' }}</p>
        </div>
        <div class="sf-actions">
            <a href="{{ route('cms.filters.index', ['page' => $filter->page]) }}" class="sf-btn sf-btn--ghost"><i class="fas fa-arrow-left"></i> Back to filters</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" style="border-radius: 14px;">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="sfw-intro">
        <span class="sfw-intro__icon"><i class="fas fa-lightbulb"></i></span>
        <div>
            <strong>A filter helps visitors narrow down the listings.</strong>
            <p>@if($fixed) This Home search filter is always there — you can only change its wording and its choices. @else Answer the steps below — the preview on the right shows exactly what visitors will see. @endif</p>
        </div>
    </div>

    <div class="sf-grid">
        <div>
            @if($fixed)
                <input type="hidden" name="page" value="{{ $filter->page }}">
                <input type="hidden" name="field" value="{{ $filter->field }}">
                <input type="hidden" name="type" value="{{ $filter->type }}">
                <section class="sfw-step">
                    <div class="sfw-locked">
                        <span class="sfw-locked__icon"><i class="fas fa-lock"></i></span>
                        <div>
                            <div class="sfw-step__title">{{ $fieldLabel }} — on the Home search</div>
                            <div class="sfw-step__sub">{{ \App\Support\SearchFilterFields::HELP[$filter->field][0] ?? '' }} It always shows as {{ strtolower($typeText[$filter->type][0] ?? $filter->type) }}{{ in_array($filter->field, ['listing_type', 'completion_status'], true) ? ' (the tabs above the search box)' : '' }}.</div>
                        </div>
                    </div>
                </section>
            @else
                {{-- Step 1: what it filters by --}}
                <section class="sfw-step">
                    <div class="sfw-step__head">
                        <span class="sfw-step__num">1</span>
                        <div><div class="sfw-step__title">What should visitors filter by?</div><div class="sfw-step__sub">Pick one thing from the list.</div></div>
                    </div>
                    <div class="sfw-row">
                        <div>
                            <label class="form-label" for="pageSelect">On which page?</label>
                            <select name="page" id="pageSelect" class="form-select form-select-lg">
                                @foreach(\App\Models\SearchFilter::listingPages() as $key => $name)
                                    <option value="{{ $key }}" {{ $current['page'] === $key ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="fieldSelect">Filter by</label>
                            <select name="field" id="fieldSelect" class="form-select form-select-lg" required>
                                <option value="" disabled {{ $current['field'] ? '' : 'selected' }}>Choose…</option>
                                @foreach($groups as $group => $groupFields)
                                    <optgroup label="{{ $group }}">
                                        @foreach($groupFields as $key => $field)
                                            <option value="{{ $key }}" {{ $current['field'] === $key ? 'selected' : '' }}>{{ $field['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="sfw-explain" id="fieldExplain" hidden>
                        <i class="fas fa-info-circle"></i>
                        <div><span id="fieldHelp"></span><em id="fieldExample"></em></div>
                    </div>
                    <div class="sf-help mt-2" id="usedNote" hidden><i class="fas fa-exclamation-circle text-warning"></i> Greyed-out items are already on this page — edit that filter instead.</div>
                </section>

                {{-- Step 2: how it looks --}}
                <section class="sfw-step">
                    <div class="sfw-step__head">
                        <span class="sfw-step__num">2</span>
                        <div><div class="sfw-step__title">How should it look?</div><div class="sfw-step__sub">Only the styles that work for this filter are shown.</div></div>
                    </div>
                    <div class="sfw-styles">
                        @foreach($typeText as $key => [$name, $desc])
                            <label class="sfw-style" data-type-choice="{{ $key }}">
                                <input type="radio" name="type" value="{{ $key }}" {{ $current['type'] === $key ? 'checked' : '' }}>
                                <div class="sfw-mini">
                                    @switch($key)
                                        @case('select') <i class="box">Any ▾</i> @break
                                        @case('radio') <i class="pill on">Any</i><i class="pill">1</i><i class="pill">2</i><i class="pill">3</i> @break
                                        @case('checkbox') <i class="tick"><b class="on"></b>Gym</i><i class="tick"><b></b>Pool</i><i class="tick"><b class="on"></b>Parking</i><i class="tick"><b></b>Balcony</i> @break
                                        @case('text') <i class="box" style="justify-content:flex-start;padding-left:.45rem">Type here…</i> @break
                                        @case('number') <i class="box" style="justify-content:flex-start;padding-left:.45rem;max-width:70px">2</i> @break
                                        @case('range') <i class="box" style="justify-content:flex-start;padding-left:.45rem">Min</i><i class="dash">–</i><i class="box" style="justify-content:flex-start;padding-left:.45rem">Max</i> @break
                                    @endswitch
                                </div>
                                <strong>{{ $name }}</strong>
                                <span class="d">{{ $desc }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="sfw-one" id="oneStyle" hidden><i class="fas fa-check-circle text-success"></i> This filter has one style, already chosen.</div>
                </section>
            @endif

            {{-- Step: wording --}}
            <section class="sfw-step">
                <div class="sfw-step__head">
                    <span class="sfw-step__num">{{ $fixed ? 1 : 3 }}</span>
                    <div><div class="sfw-step__title">What do visitors read?</div><div class="sfw-step__sub">The title above the filter{{ $languages->count() > 1 ? ', in each language' : '' }}.</div></div>
                </div>
                @if($languages->count() > 1)
                <ul class="nav sf-langs" role="tablist">
                    @foreach($languages as $lang)
                        <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#lang-{{ $lang->code }}">{{ $lang->name }}</button></li>
                    @endforeach
                </ul>
                @endif
                <div class="tab-content">
                    @foreach($languages as $lang)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="lang-{{ $lang->code }}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Title @if($lang->code === $fallback)<span class="text-danger">*</span>@endif</label>
                                    <input type="text" name="translations[{{ $lang->code }}][label]" class="form-control form-control-lg js-label" data-lang="{{ $lang->code }}" maxlength="120"
                                           value="{{ $translations[$lang->code]['label'] ?? '' }}" placeholder="e.g. Property type" {{ $lang->code === $fallback ? 'required' : '' }} dir="auto">
                                    @if($lang->code !== $fallback)<div class="sf-help">Leave empty to use the {{ strtoupper($fallback) }} title.</div>@endif
                                </div>
                                <div class="col-md-6 js-placeholder-wrap">
                                    <label class="form-label">Text inside the empty box <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="text" name="translations[{{ $lang->code }}][placeholder]" class="form-control form-control-lg js-placeholder" data-lang="{{ $lang->code }}" maxlength="120"
                                           value="{{ $translations[$lang->code]['placeholder'] ?? '' }}" placeholder="e.g. Any type" dir="auto">
                                    <div class="sf-help">Shown before the visitor picks or types anything.</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Step: choices --}}
            <section class="sfw-step" id="optionsSection">
                <div class="sfw-step__head">
                    <span class="sfw-step__num">{{ $fixed ? 2 : 4 }}</span>
                    <div><div class="sfw-step__title" id="optionsTitle">Which choices do visitors get?</div><div class="sfw-step__sub" id="optionsHint">Use them all automatically, or make your own list.</div></div>
                </div>
                <div class="sfw-source">
                    <label>
                        <input type="radio" name="options_source" value="auto" {{ $current['source'] !== 'custom' ? 'checked' : '' }}>
                        <strong><span id="autoTitle">Automatic</span> <span class="sfw-badge">Recommended</span></strong>
                        <span id="autoHint">Every value from your listings — new ones appear by themselves.</span>
                    </label>
                    <label>
                        <input type="radio" name="options_source" value="custom" {{ $current['source'] === 'custom' ? 'checked' : '' }}>
                        <strong>Pick my own</strong>
                        <span>Choose which ones show, rename them and set the order.</span>
                    </label>
                </div>

                <div id="autoOptions">
                    <div class="form-label">Visitors will get these choices:</div>
                    <div class="sf-db-preview" id="dbPreview"><span class="sf-help">Choose what to filter by first.</span></div>
                    <div class="sf-help mt-1" id="dbPreviewNote"></div>
                </div>

                <div id="customOptions">
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <button type="button" class="sf-btn sf-btn--ghost" id="fillFromDb"><i class="fas fa-magic"></i> <span id="fillLabel">Fill in all the current choices</span></button>
                        <button type="button" class="sf-btn sf-btn--ghost" id="addOption"><i class="fas fa-plus"></i> Add one</button>
                    </div>
                    <table class="sf-options">
                        <thead>
                            <tr>
                                <th style="width: 28px;"></th>
                                <th>Choice</th>
                                @foreach($languages as $lang)<th>Shown as ({{ strtoupper($lang->code) }})</th>@endforeach
                                <th style="width: 60px;">Show</th>
                                <th style="width: 44px;"></th>
                            </tr>
                        </thead>
                        <tbody id="optionRows"></tbody>
                    </table>
                    <div class="sf-help mt-2" id="customHelp"></div>
                </div>
            </section>

            {{-- Step: numbers --}}
            <section class="sfw-step" id="numberSection">
                <div class="sfw-step__head">
                    <span class="sfw-step__num">{{ $fixed ? 3 : 4 }}</span>
                    <div><div class="sfw-step__title">Number settings <span class="text-muted fw-normal" style="font-size:.8rem">(optional)</span></div><div class="sfw-step__sub">Leave them empty and they follow your listings automatically.</div></div>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Lowest</label>
                        <input type="number" name="settings[min]" class="form-control" min="0" step="any" value="{{ $settings['min'] ?? '' }}" placeholder="0">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Highest</label>
                        <input type="number" name="settings[max]" class="form-control" min="0" step="any" value="{{ $settings['max'] ?? '' }}" placeholder="Automatic">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Steps of</label>
                        <input type="number" name="settings[step]" class="form-control" min="0" step="any" value="{{ $settings['step'] ?? '' }}" placeholder="Automatic">
                    </div>
                    <div class="col-sm-6 col-lg-3" id="compareWrap">
                        <label class="form-label">Show listings with</label>
                        <select name="settings[compare]" class="form-select">
                            @foreach(['min' => 'At least this', 'max' => 'At most this', 'exact' => 'Exactly this'] as $k => $v)
                                <option value="{{ $k }}" {{ ($settings['compare'] ?? 'min') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>
        </div>

        {{-- Preview + save --}}
        <aside class="sfw-preview">
            <div class="sfw-preview__card">
                <div class="sfw-preview__top"><i class="fas fa-eye text-info"></i> What visitors will see <span>Live preview</span></div>
                <div class="sfw-preview__site">
                    <div class="sfw-preview__panel">
                        <div class="sf-preview__label" id="pvLabel">Filter</div>
                        <div id="pvControl"><span class="sf-help">Choose what to filter by.</span></div>
                    </div>
                </div>
                <div class="sfw-preview__foot" id="pvParams"></div>
            </div>
            <div class="sfw-save">
                @if($fixed)
                    <input type="hidden" name="status" value="1">
                @else
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="status" id="statusSwitch" value="1" {{ $current['status'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="statusSwitch">Show on the website</label>
                        <div class="sf-help">Turn off to hide it for now without deleting it.</div>
                    </div>
                @endif
                <button type="submit" class="sf-btn sf-btn--primary"><i class="fas fa-check"></i> {{ $isEdit ? 'Save changes' : 'Add this filter' }}</button>
                <a href="{{ route('cms.filters.index', ['page' => $filter->page]) }}" class="d-block text-center mt-2 sf-help">Cancel</a>
            </div>
        </aside>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const FIELDS = @json($fields);
    const HELP = @json(\App\Support\SearchFilterFields::HELP);
    const OPTION_TYPES = @json(\App\Models\SearchFilter::OPTION_TYPES);
    const LANGS = @json($languages->pluck('code'));
    const FALLBACK = @json($fallback);
    const PREVIEW_URL = @json(route('cms.filters.field-options'));
    const USED_BY_PAGE = @json($usedByPage);
    const initialOptions = @json(array_values($options));

    const form = document.getElementById('filterForm');
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Page / field are dropdowns (hidden inputs on a Home filter); type and source are radios.
    const val = (name) => {
        const el = form.querySelector(`[name="${name}"]:checked`) || form.querySelector(`select[name="${name}"], input[type="hidden"][name="${name}"]`);
        return el ? el.value : '';
    };

    let dbOptions = [];

    // ---- Step 1: the page decides which fields are free; the field explains itself ----------
    function syncUsedFields() {
        const select = $('#fieldSelect');
        if (!select) return;
        const used = USED_BY_PAGE[val('page')] || [];
        let any = false;
        Array.from(select.options).forEach((opt) => {
            if (!opt.value) return;
            const taken = used.includes(opt.value);
            opt.disabled = taken;
            opt.textContent = FIELDS[opt.value].label + (taken ? ' — already on this page' : '');
            any = any || taken;
        });
        if (select.selectedOptions[0]?.disabled) select.value = '';
        $('#usedNote').hidden = !any;
    }

    function syncFieldHelp() {
        const box = $('#fieldExplain');
        if (!box) return;
        const help = HELP[val('field')];
        box.hidden = !help;
        if (help) {
            $('#fieldHelp').textContent = help[0];
            $('#fieldExample').textContent = 'Example: ' + help[1];
        }
    }

    // ---- Step 2: only the styles that fit ----------------------------------------------------
    function syncTypes() {
        const field = FIELDS[val('field')];
        const cards = $$('[data-type-choice]');
        if (!cards.length) return;
        cards.forEach((card) => {
            const ok = !field || field.types.includes(card.dataset.typeChoice);
            card.hidden = !ok;
            card.querySelector('input').disabled = !ok;
        });
        if (field && !field.types.includes(val('type'))) {
            const first = form.querySelector(`input[name="type"][value="${field.types[0]}"]`);
            if (first) first.checked = true;
        }
        $('#oneStyle').hidden = !(field && field.types.length === 1);
        // A fresh title follows the field until the admin types their own.
        const label = $(`.js-label[data-lang="${FALLBACK}"]`);
        if (field && label && (!label.value || label.dataset.auto === '1')) {
            label.value = field.label.replace(/\s*\(.*\)$/, '');
            label.dataset.auto = '1';
        }
    }

    // ---- Choices: database values, a built-in set, or the price buttons ------------------------
    function optionKind() {
        const type = val('type');
        if (type === 'range' && val('field') === 'price') return 'presets';
        if (!OPTION_TYPES.includes(type)) return null;
        return FIELDS[val('field')]?.fixed ? 'fixed' : 'db';
    }
    const KIND_TEXT = {
        db: {
            title: 'Which choices do visitors get?', hint: 'Use them all automatically, or make your own list.',
            auto: ['Automatic', 'Every value from your listings and CRM › Property Options — new ones appear by themselves.'],
            fill: 'Fill in all the current choices',
            help: '"Choice" must match what listings store — use <b>Fill in all the current choices</b>, then rename, hide or drag them. For bedrooms / bathrooms you can add <code>6</code>, <code>6+</code> (6 or more) or <code>studio</code>.',
        },
        fixed: {
            title: 'Which choices do visitors get?', hint: 'This one has a built-in set of choices.',
            auto: ['All of them', 'Every built-in choice, with the site\'s own wording.'],
            fill: 'Fill in all the built-in choices',
            help: 'Pick which ones show, rename them and drag to reorder. Leave "Shown as" empty to keep the built-in wording.',
        },
        presets: {
            title: 'Quick price buttons', hint: 'The buttons under the price slider.',
            auto: ['Standard buttons', 'Under 500K, 500K – 1M, 1M – 2M … 10M+'],
            fill: 'Fill in the standard buttons',
            help: '"Choice" = <code>lowest-highest</code> in AED, e.g. <code>500000-1000000</code>. Leave the highest empty for "and up": <code>10000000-</code>. "Shown as" is the button text.',
        },
    };

    function syncSections() {
        const type = val('type');
        const kind = optionKind();
        $('#optionsSection').hidden = !kind;
        if (kind) {
            const text = KIND_TEXT[kind];
            $('#optionsTitle').textContent = text.title;
            $('#optionsHint').textContent = text.hint;
            $('#autoTitle').textContent = text.auto[0];
            $('#autoHint').textContent = text.auto[1];
            $('#fillLabel').textContent = text.fill;
            $('#customHelp').innerHTML = text.help;
            $$('#optionRows .js-value-cell').forEach(fillValueCell);
        }
        $('#numberSection').hidden = !['number', 'range'].includes(type);
        $('#compareWrap').hidden = type !== 'number';
        $$('.js-placeholder-wrap').forEach((el) => { el.hidden = !['select', 'text', 'number'].includes(type); });
        const custom = val('options_source') === 'custom';
        $('#autoOptions').hidden = custom;
        $('#customOptions').hidden = !custom;
    }

    let previewReq = 0;
    function loadDbOptions() {
        const field = val('field');
        const box = $('#dbPreview');
        if (!field || !optionKind()) { dbOptions = []; renderPreview(); return; }
        const id = ++previewReq;
        box.innerHTML = '<span class="sf-help"><i class="fas fa-spinner fa-spin"></i> Loading…</span>';
        fetch(`${PREVIEW_URL}?field=${encodeURIComponent(field)}&page=${encodeURIComponent(val('page'))}&type=${encodeURIComponent(val('type'))}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                if (id !== previewReq) return;
                dbOptions = data.options || [];
                box.innerHTML = dbOptions.length
                    ? dbOptions.map((o) => `<span class="sf-chip">${esc(o.label)}${o.count != null ? ` <em>${o.count}</em>` : ''}</span>`).join('')
                    : '<span class="sf-help">No choices yet — they appear once listings (or CRM › Property Options) have them.</span>';
                const counted = dbOptions.some((o) => o.count != null);
                $('#dbPreviewNote').textContent = (data.total > dbOptions.length ? `Showing ${dbOptions.length} of ${data.total}. ` : '')
                    + (counted ? 'The number = how many listings on this page have it. Choices with no listings are hidden by themselves.' : '');
                renderPreview();
            })
            .catch(() => { if (id === previewReq) box.innerHTML = '<span class="sf-help text-danger">Could not load the choices.</span>'; });
    }

    // ---- "Pick my own" rows -----------------------------------------------------------------
    let rowIndex = 0;
    function addRow(opt = {}) {
        const i = rowIndex++;
        const tr = document.createElement('tr');
        const labels = LANGS.map((code) => `<td><input type="text" class="form-control form-control-sm js-opt-label" data-lang="${code}" name="options[${i}][translations][${code}][label]" value="${esc(opt.translations?.[code]?.label ?? '')}" placeholder="${code === FALLBACK ? 'Same as the choice' : 'Optional'}" maxlength="120" dir="auto"></td>`).join('');
        tr.innerHTML = `
            <td class="sf-drag" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></td>
            <td class="js-value-cell" data-index="${i}" data-value="${esc(opt.value ?? '')}"></td>
            ${labels}
            <td><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="options[${i}][status]" value="1" ${opt.status === false || opt.status === 0 || opt.status === '0' ? '' : 'checked'}></div></td>
            <td><button type="button" class="sf-icon-btn is-danger js-remove" title="Remove"><i class="fas fa-times"></i></button></td>`;
        $('#optionRows').appendChild(tr);
        fillValueCell(tr.querySelector('.js-value-cell'));
    }

    /** Built-in sets: pick the choice from a dropdown (only values the site understands). */
    function fillValueCell(cell) {
        const i = cell.dataset.index;
        const current = cell.querySelector('.js-opt-value')?.value ?? cell.dataset.value ?? '';
        const field = FIELDS[val('field')];
        if (optionKind() === 'fixed' && field?.fixed) {
            cell.innerHTML = `<select class="form-select form-select-sm js-opt-value" name="options[${i}][value]">${Object.keys(field.fixed)
                .map((v) => `<option value="${esc(v)}" ${v === current ? 'selected' : ''}>${esc(field.fixed[v][0])}</option>`).join('')}</select>`;
        } else {
            const ph = optionKind() === 'presets' ? 'e.g. 500000-1000000' : 'e.g. villa';
            cell.innerHTML = `<input type="text" class="form-control form-control-sm js-opt-value" name="options[${i}][value]" value="${esc(current)}" placeholder="${ph}" maxlength="100">`;
        }
    }

    $('#addOption').addEventListener('click', () => { addRow(); renderPreview(); });
    $('#fillFromDb').addEventListener('click', () => {
        const existing = $$('.js-opt-value').map((el) => el.value.trim());
        const fresh = dbOptions.filter((o) => !existing.includes(String(o.value)));
        if (!fresh.length) { alert(dbOptions.length ? 'They are all in your list already.' : 'There are no choices yet.'); return; }
        // Replace an untouched empty starter row.
        if ($$('#optionRows tr').length === 1 && !existing[0]) $('#optionRows').innerHTML = '';
        fresh.forEach((o) => addRow({ value: o.value, translations: optionKind() === 'fixed' ? {} : { [FALLBACK]: { label: o.label } } }));
        renderPreview();
    });
    $('#optionRows').addEventListener('click', (e) => {
        if (e.target.closest('.js-remove')) { e.target.closest('tr').remove(); renderPreview(); }
    });
    Sortable.create($('#optionRows'), { handle: '.sf-drag', animation: 150, onEnd: renderPreview });

    // ---- Live preview ------------------------------------------------------------------------
    function previewOptions() {
        const field = FIELDS[val('field')];
        const list = val('options_source') === 'custom'
            ? $$('#optionRows tr').map((tr) => {
                const value = $('.js-opt-value', tr)?.value.trim();
                const label = $(`.js-opt-label[data-lang="${FALLBACK}"]`, tr)?.value.trim();
                const on = tr.querySelector('input[type="checkbox"]')?.checked;
                return value && on ? { value, label: label || field?.fixed?.[value]?.[0] || value } : null;
            }).filter(Boolean)
            : dbOptions;
        return list;
    }

    function renderPreview() {
        const type = val('type');
        const field = val('field');
        if (!field) {
            $('#pvLabel').textContent = 'Filter';
            $('#pvControl').innerHTML = '<span class="sf-help">Choose what to filter by.</span>';
            $('#pvParams').innerHTML = '';
            return;
        }
        const label = $(`.js-label[data-lang="${FALLBACK}"]`)?.value || FIELDS[field]?.label || 'Filter';
        const hint = $(`.js-placeholder[data-lang="${FALLBACK}"]`)?.value;
        const opts = previewOptions().slice(0, 8);
        const names = opts.length ? opts.map((o) => esc(o.label)) : ['Choice 1', 'Choice 2', 'Choice 3'];
        const min = form.querySelector('[name="settings[min]"]').value || '0';
        const max = form.querySelector('[name="settings[max]"]').value || 'Max';
        let html = '';
        if (type === 'select') {
            html = `<select class="form-select"><option>${esc(hint || 'Any')}</option>${names.map((n) => `<option>${n}</option>`).join('')}</select>`;
        } else if (type === 'radio') {
            html = `<div class="sf-preview__pills"><span class="is-on">Any</span>${names.map((n) => `<span>${n}</span>`).join('')}</div>`;
        } else if (type === 'checkbox') {
            html = `<div class="sf-preview__checks">${names.map((n, i) => `<label><input type="checkbox" ${i === 0 ? 'checked' : ''}> ${n}</label>`).join('')}</div>`;
        } else if (type === 'text') {
            html = `<input type="text" class="form-control" placeholder="${esc(hint || 'Type to search…')}">`;
        } else if (type === 'number') {
            html = `<input type="number" class="form-control" placeholder="${esc(hint || min)}">`;
        } else if (type === 'range') {
            html = `<div class="sf-preview__range"><div><b>Min</b>${esc(min)}</div><div><b>Max</b>${esc(max)}</div></div>`;
            if (optionKind() === 'presets' && opts.length) html += `<div class="sf-preview__pills mt-3">${names.map((n) => `<span>${n}</span>`).join('')}</div>`;
        }
        $('#pvLabel').textContent = label;
        $('#pvControl').innerHTML = html;

        // For developers: the parameter the website sends (key-value API).
        const compare = form.querySelector('[name="settings[compare]"]').value;
        const sample = opts[0]?.value ?? 'value';
        let params;
        if (type === 'checkbox') params = `?${field}=${esc(sample)},${esc(opts[1]?.value ?? 'other')}`;
        else if (OPTION_TYPES.includes(type)) params = `?${field}=${esc(sample)}`;
        else if (type === 'text') params = `?${field}=text`;
        else if (type === 'range') params = `?min_${field}=…&max_${field}=…`;
        else params = `?${compare === 'exact' ? field : compare + '_' + field}=…`;
        $('#pvParams').innerHTML = `<i class="fas fa-code me-1"></i> For developers — the website sends <code>${params}</code>`;
    }

    // ---- Wiring ------------------------------------------------------------------------------
    form.addEventListener('change', (e) => {
        if (e.target.name === 'page') { syncUsedFields(); syncFieldHelp(); syncTypes(); loadDbOptions(); }
        if (e.target.name === 'field') { syncFieldHelp(); syncTypes(); syncSections(); loadDbOptions(); }
        if (e.target.name === 'type') { syncSections(); loadDbOptions(); }
        syncSections();
        renderPreview();
    });
    form.addEventListener('input', (e) => {
        if (e.target.classList.contains('js-label')) e.target.dataset.auto = '0';
        renderPreview();
    });

    syncUsedFields();
    syncFieldHelp();
    syncTypes();
    initialOptions.forEach((o) => addRow(o));
    if (!initialOptions.length) addRow();
    syncSections();
    loadDbOptions();
    renderPreview();
})();
</script>
@endpush
