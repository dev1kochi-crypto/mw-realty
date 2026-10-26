@php
    $isEdit = isset($plan);
    $showLanguageUi = config('cms-kit.common.modules.languages', true) && count($languages) > 1;
    $currentCycle = old('billing_cycle', $plan->billing_cycle ?? 'monthly');
    $period = old('featured_period', $plan->featured_period ?? 'concurrent');
    $adMax = (int) old('ad_max_hours', $plan->ad_max_hours ?? 0);
    $planFeatures = old('plan_features', $plan->plan_features ?? []);
    $featureIcons = [
        'lead_insights' => 'fa-chart-line', 'property_insights' => 'fa-magnifying-glass-chart', 'listing_settings' => 'fa-stamp',
        'facebook' => 'fa-facebook-f', 'property_finder' => 'fa-building',
    ];
    $featureHelp = [
        'lead_insights' => 'Charts of where leads come from and how they convert.',
        'property_insights' => 'Views, clicks and leads per listing.',
        'listing_settings' => 'Their own watermark on listing photos.',
        'facebook' => 'Leads from Facebook Lead Ads arrive in the CRM.',
        'property_finder' => 'Import and sync listings from Property Finder.',
    ];
@endphp

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    .pf { --pf-navy: var(--secondary-color, #264373); --pf-teal: var(--primary-color, #04a1cc); --pf-ink: #1c2340; --pf-muted: #838aa3; --pf-soft: #f7f8fc; --pf-border: rgba(28, 35, 64, 0.08); }
    .pf-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
    .pf-head h4 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--pf-ink); margin: 0; }
    .pf-head p { color: var(--pf-muted); font-size: .85rem; margin: .2rem 0 0; }
    .pf-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 1.5rem; align-items: start; }
    @media (max-width: 1199.98px) { .pf-grid { grid-template-columns: 1fr; } }

    .pf-section { background: #fff; border: 1px solid var(--pf-border); border-radius: 18px; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); padding: 1.35rem 1.5rem; margin-bottom: 1.25rem; }
    .pf-section__head { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1.1rem; }
    .pf-section__icon { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, var(--pf-navy), #3a5794); }
    .pf-section__icon.is-teal { background: linear-gradient(135deg, var(--pf-teal), #2fc4e8); }
    .pf-section__icon.is-amber { background: linear-gradient(135deg, #e08e0b, #f5a623); }
    .pf-section__icon.is-purple { background: linear-gradient(135deg, #6d28d9, #8b5cf6); }
    .pf-section__icon.is-green { background: linear-gradient(135deg, #0f9d58, #34c880); }
    .pf-section__icon.is-red { background: linear-gradient(135deg, #ca2844, #e5546d); }
    .pf-section__title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.02rem; color: var(--pf-ink); }
    .pf-section__hint { font-size: .8rem; color: var(--pf-muted); margin-top: .1rem; }
    .pf .form-label { font-size: .8rem; font-weight: 700; color: #3d4460; margin-bottom: .35rem; }
    .pf .form-control, .pf .form-select { border-radius: 10px; }
    .pf-help { font-size: .74rem; color: var(--pf-muted); margin-top: .3rem; }

    .pf-langs { display: inline-flex; gap: .25rem; padding: .25rem; border-radius: 12px; background: var(--pf-soft); margin-bottom: 1rem; }
    .pf-langs .nav-link { border-radius: 9px; padding: .35rem .9rem; font-size: .82rem; font-weight: 700; color: var(--pf-muted); }
    .pf-langs .nav-link.active { background: #fff; color: var(--pf-navy); box-shadow: 0 2px 8px rgba(28, 35, 64, .1); }

    .pf-cycles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; }
    @media (max-width: 767.98px) { .pf-cycles { grid-template-columns: repeat(2, 1fr); } }
    .pf-cycle { position: relative; border: 1.5px solid var(--pf-border); border-radius: 12px; padding: .6rem .75rem; cursor: pointer; transition: all .2s; background: #fff; }
    .pf-cycle input { position: absolute; opacity: 0; }
    .pf-cycle strong { display: block; font-size: .86rem; color: var(--pf-ink); }
    .pf-cycle span { font-size: .72rem; color: var(--pf-muted); }
    .pf-cycle:has(input:checked) { border-color: var(--pf-teal); box-shadow: 0 0 0 3px rgba(4, 161, 204, .12); }
    .pf-money { position: relative; }
    .pf-money > span { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); font-size: .75rem; font-weight: 800; color: var(--pf-muted); }
    .pf-money .form-control { padding-left: 3rem; font-weight: 700; }
    .pf-save-chip { display: inline-block; margin-top: .35rem; padding: .15rem .55rem; border-radius: 99px; font-size: .72rem; font-weight: 800; background: rgba(15, 157, 88, .1); color: #0f9d58; }

    .pf-limit { border: 1px solid var(--pf-border); border-radius: 14px; padding: .9rem 1rem; background: var(--pf-soft); height: 100%; }
    .pf-limit__label { display: flex; align-items: center; gap: .5rem; font-weight: 800; font-size: .84rem; color: var(--pf-ink); margin-bottom: .55rem; }
    .pf-limit__label i { color: var(--pf-teal); }

    .pf-durations { display: flex; flex-wrap: wrap; gap: .4rem; }
    .pf-duration { position: relative; }
    .pf-duration input { position: absolute; opacity: 0; }
    .pf-duration span { display: inline-block; border: 1.5px solid var(--pf-border); background: #fff; border-radius: 99px; padding: .3rem .8rem; font-size: .8rem; font-weight: 700; color: #3d4460; cursor: pointer; transition: all .2s; }
    .pf-duration input:checked + span { background: var(--pf-navy); border-color: var(--pf-navy); color: #fff; }
    .pf-duration input:focus-visible + span { box-shadow: 0 0 0 3px rgba(38, 67, 115, .2); }
    .pf-duration.is-none input:checked + span { background: #8891a8; border-color: #8891a8; }

    .pf-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: .65rem; }
    .pf-tile { position: relative; display: flex; gap: .7rem; align-items: flex-start; padding: .8rem .9rem; border: 1.5px solid var(--pf-border); border-radius: 14px; cursor: pointer; background: #fff; transition: all .2s; }
    .pf-tile input { position: absolute; opacity: 0; }
    .pf-tile__icon { width: 34px; height: 34px; flex-shrink: 0; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: var(--pf-soft); color: var(--pf-muted); transition: all .2s; }
    .pf-tile__title { font-weight: 800; font-size: .84rem; color: var(--pf-ink); }
    .pf-tile__text { font-size: .72rem; color: var(--pf-muted); line-height: 1.35; }
    .pf-tile__check { margin-left: auto; width: 20px; height: 20px; flex-shrink: 0; border-radius: 50%; border: 1.5px solid #cfd3e1; display: flex; align-items: center; justify-content: center; font-size: .6rem; color: transparent; transition: all .2s; }
    .pf-tile:has(input:checked) { border-color: var(--pf-teal); background: linear-gradient(135deg, #fff, #f0fbfe); }
    .pf-tile:has(input:checked) .pf-tile__icon { background: var(--pf-teal); color: #fff; }
    .pf-tile:has(input:checked) .pf-tile__check { background: var(--pf-teal); border-color: var(--pf-teal); color: #fff; }

    .pf-switch-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 0; border-top: 1px dashed var(--pf-border); }
    .pf-switch-row:first-of-type { border-top: 0; padding-top: 0; }
    .pf-switch-row strong { display: block; font-size: .86rem; color: var(--pf-ink); }
    .pf-switch-row span { font-size: .74rem; color: var(--pf-muted); }
    .pf-switch-row .form-check-input { width: 2.6em; height: 1.35em; cursor: pointer; }

    /* live preview — the pricing card as agents see it */
    .pf-aside { position: sticky; top: 90px; }
    .pf-aside__title { font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--pf-muted); margin-bottom: .6rem; }
    .pf-card { border-radius: 20px; background: #fff; border: 1px solid var(--pf-border); box-shadow: 0 16px 40px rgba(28, 35, 64, .12); overflow: hidden; transition: opacity .2s; }
    .pf-card.is-popular { border: 2px solid var(--pf-teal); }
    .pf-card.is-inactive { opacity: .55; }
    .pf-card__head { position: relative; overflow: hidden; padding: 1.2rem 1.25rem 1rem; color: #fff; background: linear-gradient(135deg, var(--pf-navy), #3a5794); }
    .pf-card__head::after { content: ''; position: absolute; right: -40px; top: -40px; width: 130px; height: 130px; border-radius: 50%; background: rgba(255, 255, 255, .1); }
    .pf-card__badge { position: absolute; top: .9rem; right: .9rem; z-index: 1; font-size: .6rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; padding: .22rem .55rem; border-radius: 99px; background: linear-gradient(135deg, #e08e0b, #f5a623); }
    .pf-card__badge.is-off { background: rgba(0, 0, 0, .3); right: auto; left: auto; position: static; display: inline-block; margin-left: .35rem; }
    .pf-card__name { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; }
    .pf-card__desc { font-size: .78rem; opacity: .85; min-height: 1.1em; }
    .pf-card__price { margin-top: .7rem; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 2rem; line-height: 1; }
    .pf-card__price small { font-size: .78rem; font-weight: 700; opacity: .8; }
    .pf-card__price sup { font-size: .75rem; font-weight: 700; opacity: .85; top: -1.1em; margin-right: .2rem; }
    .pf-card__yearly { font-size: .74rem; opacity: .9; margin-top: .3rem; min-height: 1.1em; }
    .pf-card__list { list-style: none; margin: 0; padding: 1rem 1.25rem; display: grid; gap: .45rem; max-height: 52vh; overflow: auto; }
    .pf-card__list li { display: flex; gap: .5rem; font-size: .8rem; color: #3d4460; line-height: 1.35; }
    .pf-card__list li i { width: 1rem; margin-top: .15rem; font-size: .72rem; color: #0f9d58; flex-shrink: 0; }
    .pf-card__list li.is-off { color: #a3a9bd; text-decoration: line-through; }
    .pf-card__list li.is-off i { color: #c5c9d6; }
    .pf-card__list li.is-extra i { color: var(--pf-teal); }
    .pf-aside__note { font-size: .74rem; color: var(--pf-muted); margin-top: .7rem; }

    .pf-bar { position: sticky; bottom: 1rem; z-index: 20; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: .8rem 1.1rem; margin-top: .5rem;
        border-radius: 16px; background: rgba(255, 255, 255, .96); border: 1px solid var(--pf-border); box-shadow: 0 14px 34px rgba(28, 35, 64, .15); backdrop-filter: blur(6px); }
    .pf-bar__text { font-size: .8rem; color: var(--pf-muted); }
</style>
@endpush

<div class="pf">
    <div class="pf-head">
        <div>
            <h4>{{ $isEdit ? 'Edit plan' : 'Add plan' }}</h4>
            <p>What agencies and agents get on this plan. Limits and features are enforced in their CRM and listed on the pricing card automatically — the preview on the right shows how it looks.</p>
        </div>
        <a href="{{ route('cms.plans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> All plans</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="pf-grid">
        <div>
            {{-- 1. Name & description --}}
            <div class="pf-section">
                <div class="pf-section__head">
                    <span class="pf-section__icon"><i class="fas fa-tag"></i></span>
                    <div><div class="pf-section__title">Name &amp; description</div><div class="pf-section__hint">What customers read on the pricing card, per language.</div></div>
                </div>
                @if($showLanguageUi && count($languages) > 1)
                <ul class="nav pf-langs" id="langTabs" role="tablist">
                    @foreach($languages as $lang)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $lang->code }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $lang->code }}-content" type="button" role="tab">
                            <i class="fas fa-language me-1 opacity-75"></i>{{ $lang->name }}
                        </button>
                    </li>
                    @endforeach
                </ul>
                @endif
                <div class="tab-content">
                    @foreach($languages as $lang)
                    @php $t = $isEdit ? ($plan->translations[$lang->code] ?? []) : []; @endphp
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $lang->code }}-content" role="tabpanel" data-lang="{{ $lang->code }}" data-first="{{ $loop->first ? 1 : 0 }}">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Plan name <span class="text-danger">*</span></label>
                                <input type="text" name="translations[{{ $lang->code }}][name]" class="form-control pf-watch @error("translations.{$lang->code}.name") is-invalid @enderror" value="{{ old("translations.{$lang->code}.name", $t['name'] ?? '') }}" placeholder="e.g. Basic, Pro" data-preview="name" required>
                                @error("translations.{$lang->code}.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Short description</label>
                                <input type="text" name="translations[{{ $lang->code }}][description]" class="form-control pf-watch" value="{{ old("translations.{$lang->code}.description", $t['description'] ?? '') }}" placeholder="e.g. For growing agencies" data-preview="desc">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Extra lines on the card</label>
                                <textarea name="features[{{ $lang->code }}]" class="form-control pf-watch" rows="3" data-preview="extra" placeholder="One per line, e.g.&#10;Priority support&#10;Dedicated account manager">{{ old("features.{$lang->code}", implode("\n", $t['features'] ?? [])) }}</textarea>
                                <div class="pf-help">Only things that aren't limits or features below (support level, account manager…). Those are listed on the card automatically.</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- 2. Pricing --}}
            <div class="pf-section">
                <div class="pf-section__head">
                    <span class="pf-section__icon is-teal"><i class="fas fa-coins"></i></span>
                    <div><div class="pf-section__title">Pricing</div><div class="pf-section__hint">How the plan is billed. Customers can pick monthly or — if you set a yearly price — yearly.</div></div>
                </div>
                <label class="form-label">Billing <span class="text-danger">*</span></label>
                <div class="pf-cycles mb-3">
                    @foreach(['free' => ['Free', 'No payment'], 'monthly' => ['Monthly', 'Billed every month'], 'yearly' => ['Yearly', 'Billed once a year'], 'one_time' => ['One-time', 'Paid once']] as $value => [$label, $sub])
                        <label class="pf-cycle"><input type="radio" name="billing_cycle" value="{{ $value }}" class="pf-watch" @checked($currentCycle === $value) required><strong>{{ $label }}</strong><span>{{ $sub }}</span></label>
                    @endforeach
                </div>
                @error('billing_cycle')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="monthlyPrice">Monthly price <span class="text-danger">*</span></label>
                        <div class="pf-money"><span>AED</span><input type="number" step="0.01" min="0" name="price" id="monthlyPrice" class="form-control pf-watch @error('price') is-invalid @enderror" value="{{ old('price', $plan->price ?? 0) }}" required></div>
                        @error('price')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="yearlyPrice">Yearly price</label>
                        <div class="pf-money"><span>AED</span><input type="number" step="0.01" min="0" name="yearly_price" id="yearlyPrice" class="form-control pf-watch @error('yearly_price') is-invalid @enderror" value="{{ old('yearly_price', $plan->yearly_price ?? '') }}" placeholder="Monthly only"></div>
                        <div id="yearlyHint" class="pf-help">Tip: 10 × monthly = 2 months free.</div>
                        @error('yearly_price')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="orderIndex">Position on the pricing page</label>
                        <input type="number" name="order_index" id="orderIndex" class="form-control" value="{{ old('order_index', $plan->order_index ?? ($nextOrder ?? 1)) }}" min="1">
                        <div class="pf-help">1 = first (left).</div>
                    </div>
                </div>
            </div>

            {{-- 3. Limits --}}
            <div class="pf-section">
                <div class="pf-section__head">
                    <span class="pf-section__icon is-purple"><i class="fas fa-sliders"></i></span>
                    <div><div class="pf-section__title">Limits</div><div class="pf-section__hint">Enforced in their CRM. Leave a box empty for unlimited where it says so.</div></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-xl-4">
                        <div class="pf-limit">
                            <div class="pf-limit__label"><i class="fas fa-building"></i> Listings</div>
                            <input type="number" min="0" name="property_limit" class="form-control pf-watch" value="{{ old('property_limit', $plan->property_limit ?? '') }}" placeholder="Unlimited">
                            <div class="pf-help">Paid plans: new listings per month. Free: listings at a time. Empty = unlimited.</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <div class="pf-limit">
                            <div class="pf-limit__label"><i class="fas fa-star"></i> Premium listings</div>
                            <div class="input-group">
                                <input type="number" min="0" name="featured_per_month" class="form-control pf-watch @error('featured_per_month') is-invalid @enderror" value="{{ old('featured_per_month', $plan->featured_per_month ?? 0) }}">
                                <select name="featured_period" class="form-select pf-watch" style="max-width: 52%;">
                                    <option value="concurrent" @selected($period === 'concurrent')>at a time</option>
                                    <option value="month" @selected($period === 'month')>per month</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <span class="pf-help m-0 text-nowrap">Max days each</span>
                                <input type="number" min="1" name="featured_max_days" class="form-control form-control-sm pf-watch @error('featured_max_days') is-invalid @enderror" value="{{ old('featured_max_days', $plan->featured_max_days ?? '') }}" placeholder="No limit">
                            </div>
                            <div class="pf-help">0 = not included.</div>
                            @error('featured_per_month')<div class="text-danger small">{{ $message }}</div>@enderror
                            @error('featured_max_days')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <div class="pf-limit">
                            <div class="pf-limit__label"><i class="fas fa-users"></i> Team agents</div>
                            <input type="number" min="0" name="agent_limit" class="form-control pf-watch @error('agent_limit') is-invalid @enderror" value="{{ old('agent_limit', $isEdit ? $plan->agent_limit : 0) }}" placeholder="Unlimited">
                            <div class="pf-help">Agents an agency can add under this plan. 0 = none, empty = unlimited.</div>
                            @error('agent_limit')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Advertising --}}
            <div class="pf-section">
                <div class="pf-section__head">
                    <span class="pf-section__icon is-red"><i class="fas fa-rectangle-ad"></i></span>
                    <div><div class="pf-section__title">Website advertising</div><div class="pf-section__hint">Banner ads they book from CRM › Advertisements, shown in the website's ad sliders after MW Realty approves them.</div></div>
                </div>
                <label class="form-label">Longest ad they can book</label>
                <div class="pf-durations mb-3">
                    <label class="pf-duration is-none"><input type="radio" name="ad_max_hours" value="0" class="pf-watch" @checked($adMax === 0)><span>Not included</span></label>
                    @foreach(\App\Services\AdBookingService::DURATIONS as $hours)
                        <label class="pf-duration"><input type="radio" name="ad_max_hours" value="{{ $hours }}" class="pf-watch" @checked($adMax === $hours)><span>{{ \App\Models\Plan::adDurationLabel($hours) }}</span></label>
                    @endforeach
                </div>
                @error('ad_max_hours')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <div class="row g-3" id="adsAllowance">
                    <div class="col-md-5">
                        <label class="form-label" for="adsPerMonth">Ads included per month</label>
                        <input type="number" min="0" name="ads_per_month" id="adsPerMonth" class="form-control pf-watch @error('ads_per_month') is-invalid @enderror" value="{{ old('ads_per_month', $plan->ads_per_month ?? 0) }}">
                        @error('ads_per_month')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-7 d-flex align-items-end">
                        <div class="pf-help">0 = every ad is paid with credits. Once the included ads are used they can still book more with credits — prices per page are in CRM › Advertisements › Placements &amp; pricing.</div>
                    </div>
                </div>
            </div>

            {{-- 5. CRM features --}}
            <div class="pf-section">
                <div class="pf-section__head">
                    <span class="pf-section__icon is-green"><i class="fas fa-puzzle-piece"></i></span>
                    <div><div class="pf-section__title">CRM features</div><div class="pf-section__hint">Sections this plan unlocks. On plans without them, the menu item shows a padlock and an upgrade message.</div></div>
                </div>
                <div class="pf-tiles">
                    <label class="pf-tile">
                        <input type="checkbox" name="reports_access" class="pf-watch" data-feature="Leads reports & analytics" {{ old('reports_access', $plan->reports_access ?? false) ? 'checked' : '' }}>
                        <span class="pf-tile__icon"><i class="fas fa-chart-pie"></i></span>
                        <span><span class="pf-tile__title d-block">Leads reports &amp; analytics</span><span class="pf-tile__text">The Reports section: leads, listings, sales and agents.</span></span>
                        <span class="pf-tile__check"><i class="fas fa-check"></i></span>
                    </label>
                    @foreach(\App\Models\Plan::GATED_FEATURES as $key => $label)
                        <label class="pf-tile">
                            <input type="checkbox" name="plan_features[]" value="{{ $key }}" class="pf-watch" data-feature="{{ $label }}" @checked(in_array($key, $planFeatures, true))>
                            <span class="pf-tile__icon"><i class="{{ $key === 'facebook' ? 'fab' : 'fas' }} {{ $featureIcons[$key] ?? 'fa-check' }}"></i></span>
                            <span><span class="pf-tile__title d-block">{{ $label }}</span><span class="pf-tile__text">{{ $featureHelp[$key] ?? '' }}</span></span>
                            <span class="pf-tile__check"><i class="fas fa-check"></i></span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- 6. Visibility --}}
            <div class="pf-section">
                <div class="pf-section__head">
                    <span class="pf-section__icon is-amber"><i class="fas fa-eye"></i></span>
                    <div><div class="pf-section__title">Visibility</div><div class="pf-section__hint">Whether customers can choose this plan, and whether it's highlighted.</div></div>
                </div>
                <div class="pf-switch-row">
                    <div><strong>Active</strong><span>Shown on the pricing page and can be chosen. Turning it off doesn't move existing customers.</span></div>
                    <div class="form-check form-switch m-0"><input class="form-check-input pf-watch" type="checkbox" name="status" id="planStatus" {{ old('status', $plan->status ?? true) ? 'checked' : '' }}></div>
                </div>
                <div class="pf-switch-row">
                    <div><strong>Mark as "Popular"</strong><span>Highlights this card on the pricing page.</span></div>
                    <div class="form-check form-switch m-0"><input class="form-check-input pf-watch" type="checkbox" name="is_popular" id="isPopular" {{ old('is_popular', $plan->is_popular ?? false) ? 'checked' : '' }}></div>
                </div>
            </div>
        </div>

        {{-- Live preview --}}
        <aside class="pf-aside">
            <div class="pf-aside__title"><i class="fas fa-eye me-1"></i> Live preview</div>
            <div class="pf-card" id="pfCard">
                <div class="pf-card__head">
                    <span class="pf-card__badge" id="pfPopular">Popular</span>
                    <div class="pf-card__name"><span id="pfName">Plan name</span><span class="pf-card__badge is-off" id="pfInactive">Inactive</span></div>
                    <div class="pf-card__desc" id="pfDesc"></div>
                    <div class="pf-card__price" id="pfPrice"></div>
                    <div class="pf-card__yearly" id="pfYearly"></div>
                </div>
                <ul class="pf-card__list" id="pfList"></ul>
            </div>
            <div class="pf-aside__note">Agents see this card on CRM › Plans and the website's pricing page.</div>
        </aside>
    </div>

    <div class="pf-bar">
        <span class="pf-bar__text"><i class="fas fa-circle-info me-1"></i> Changes apply to everyone on this plan as soon as you save.</span>
        <div class="d-flex gap-2">
            <a href="{{ route('cms.plans.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> {{ $isEdit ? 'Save changes' : 'Create plan' }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.querySelector('.pf').closest('form');
    const val = (name) => form.querySelector(`[name="${name}"]`);
    const checked = (name) => form.querySelector(`[name="${name}"]:checked`)?.value;
    const num = (el) => (el && el.value !== '' ? Number(el.value) : null);
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
    const adLabel = (h) => (h >= 24 && h % 24 === 0 ? plural(h / 24, 'day') : plural(h, 'hour'));
    const money = (n) => Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });
    const first = form.querySelector('.tab-pane[data-first="1"]');
    const $ = (id) => document.getElementById(id);

    // Mirrors Plan::entitlementLines() — what the real card lists.
    function lines() {
        const cycle = checked('billing_cycle');
        const listings = num(val('property_limit'));
        const premium = num(val('featured_per_month')) || 0;
        const days = num(val('featured_max_days'));
        const agents = num(val('agent_limit'));
        const adMax = Number(checked('ad_max_hours') || 0);
        const adsMonth = num(val('ads_per_month')) || 0;
        const out = [];

        out.push([listings === null ? 'Unlimited listings' : `${plural(listings, 'listing')}${cycle !== 'free' ? ' / month' : ''}`, true]);
        out.push([premium > 0 ? `${plural(premium, 'premium listing')}${val('featured_period').value === 'month' ? ' / month' : ''}${days ? ` (max ${days} days)` : ''}` : 'Premium listings', premium > 0]);
        if (adMax > 0) {
            out.push([adsMonth > 0 ? `${plural(adsMonth, 'website banner ad')} / month` : 'Website banner ads (paid with credits)', true]);
            out.push([`Ads run up to ${adLabel(adMax)} each`, true]);
            out.push(['Ad impressions & clicks report', true]);
        } else {
            out.push(['Website banner ads', false]);
        }
        out.push([agents === null ? 'Unlimited team agents' : (agents > 0 ? `Add up to ${plural(agents, 'team agent')}` : 'Team agent accounts'), agents === null || agents > 0]);
        form.querySelectorAll('.pf-tile input').forEach((el) => out.push([el.dataset.feature, el.checked]));
        return out;
    }

    function render() {
        const cycle = checked('billing_cycle');
        const price = Number(val('price').value) || 0;
        const yearly = Number(val('yearly_price').value) || 0;
        const name = first?.querySelector('[data-preview="name"]').value.trim();
        const extra = (first?.querySelector('[data-preview="extra"]').value || '').split('\n').map((s) => s.trim()).filter(Boolean);

        $('pfName').textContent = name || 'Plan name';
        $('pfDesc').textContent = first?.querySelector('[data-preview="desc"]').value.trim() || '';
        $('pfPrice').innerHTML = cycle === 'free' || price <= 0
            ? 'Free <small>forever</small>'
            : `<sup>AED</sup>${money(price)} <small>/ ${cycle === 'one_time' ? 'once' : (cycle === 'yearly' ? 'year' : 'month')}</small>`;
        $('pfYearly').textContent = cycle !== 'free' && yearly > 0 && price > 0
            ? `or AED ${money(yearly)} / year · save ${Math.round((1 - yearly / (price * 12)) * 100)}%` : '';
        $('pfPopular').style.display = val('is_popular').checked ? '' : 'none';
        $('pfInactive').style.display = val('status').checked ? 'none' : '';
        $('pfCard').classList.toggle('is-popular', val('is_popular').checked);
        $('pfCard').classList.toggle('is-inactive', !val('status').checked);

        const list = $('pfList');
        list.innerHTML = '';
        [...lines(), ...extra.map((t) => [t, true, true])].forEach(([text, on, isExtra]) => {
            const li = document.createElement('li');
            li.className = (on ? '' : 'is-off') + (isExtra ? ' is-extra' : '');
            li.innerHTML = `<i class="fas ${on ? 'fa-circle-check' : 'fa-circle-xmark'}"></i><span></span>`;
            li.querySelector('span').textContent = text;
            list.appendChild(li);
        });

        // Yearly saving hint + ads allowance only when ads are included.
        $('yearlyHint').textContent = price > 0 && yearly > 0
            ? `≈ AED ${(yearly / 12).toFixed(0)}/month · customers save ${Math.round((1 - yearly / (price * 12)) * 100)}% vs monthly`
            : 'Tip: 10 × monthly = 2 months free.';
        $('adsAllowance').style.opacity = Number(checked('ad_max_hours') || 0) > 0 ? '' : '.45';
    }

    form.addEventListener('input', render);
    form.addEventListener('change', render);
    render();

    // A required field on a hidden language tab → show that tab so the browser can point at it.
    document.addEventListener('invalid', function (e) {
        const pane = e.target.closest('.tab-pane');
        const btn = pane && document.querySelector(`[data-bs-target="#${pane.id}"]`);
        if (btn && !btn.classList.contains('active')) {
            bootstrap.Tab.getOrCreateInstance(btn).show();
            setTimeout(() => e.target.focus(), 150);
        }
    }, true);
})();
</script>
@endpush
