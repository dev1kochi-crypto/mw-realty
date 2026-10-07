{{-- Agency lead assignment (AgentController::updateAssignment): automatic round robin for the chosen
     kinds of lead, or manual. A modal (#leadAssignModal) opened from the slim summary bar on My Agents
     and the toolbar on Leads. Needs $assignSetting (AgencyLeadAssignmentSetting) and $agentCount. --}}
@php
    $rrSources = $assignSetting->roundRobinSources();
    $sourceMeta = [
        'property' => ['icon' => 'fas fa-building', 'hint' => 'Enquiries on listings with no assigned agent'],
        'generic' => ['icon' => 'fas fa-envelope-open-text', 'hint' => 'Profile, contact & custom requests'],
        'facebook' => ['icon' => 'fab fa-facebook-f', 'hint' => 'Leads from your connected Facebook Pages'],
    ];
@endphp
<div class="modal fade" id="leadAssignModal" tabindex="-1" aria-labelledby="leadAssignModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
<form method="POST" action="{{ route('portal.agents.lead-assignment') }}" class="lassign js-lead-assign">
    @csrf @method('PUT')
    <div class="lassign__head">
        <span class="lassign__icon" aria-hidden="true"><i class="fas fa-shuffle"></i></span>
        <div class="flex-grow-1 min-w-0">
            <div class="lassign__title" id="leadAssignModalLabel">Lead assignment</div>
            <div class="lassign__sub">Choose how new agency leads reach your agents. Listings with an assigned agent always send their enquiries to that agent.</div>
        </div>
        <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="lassign__body">
        <div class="lassign__col">
            <div class="lassign__label" id="lassignModeLabel">Assignment mode</div>
            <div class="lassign__modes" role="radiogroup" aria-labelledby="lassignModeLabel">
                @foreach(['automatic' => ['fas fa-rotate', 'Automatic', 'Round robin across ' . $agentCount . ' active agent' . ($agentCount === 1 ? '' : 's')],
                          'manual' => ['fas fa-hand-pointer', 'Manual', 'You assign every new lead yourself']] as $mode => [$icon, $title, $text])
                <label class="lassign__mode">
                    <input type="radio" name="mode" value="{{ $mode }}" class="js-lead-assign-mode" @checked($assignSetting->isManual() === ($mode === 'manual'))>
                    <span class="lassign__mode-box">
                        <span class="lassign__mode-icon"><i class="{{ $icon }}"></i></span>
                        <span class="min-w-0">
                            <span class="lassign__mode-title">{{ $title }}</span>
                            <span class="lassign__mode-text">{{ $text }}</span>
                        </span>
                        <span class="lassign__tick" aria-hidden="true"><i class="fas fa-check"></i></span>
                    </span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="lassign__col">
            <div class="js-lead-assign-auto" @if($assignSetting->isManual()) hidden @endif>
                <div class="lassign__label">Leads that rotate automatically</div>
                <div class="lassign__sources" role="group" aria-label="Leads that rotate automatically">
                    @foreach(\App\Models\AgencyLeadAssignmentSetting::SOURCES as $key => $label)
                    <label class="lassign__source">
                        <span class="lassign__source-icon lassign__source-icon--{{ $key }}" aria-hidden="true"><i class="{{ $sourceMeta[$key]['icon'] }}"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="lassign__source-title">{{ $label }}</span>
                            <span class="lassign__source-text">{{ $sourceMeta[$key]['hint'] }}</span>
                        </span>
                        <span class="form-check form-switch mb-0">
                            <input type="checkbox" class="form-check-input" role="switch" name="sources[]" value="{{ $key }}" @checked(in_array($key, $rrSources, true)) aria-label="Round robin {{ $label }}">
                        </span>
                    </label>
                    @endforeach
                </div>
                <div class="lassign__hint js-lead-assign-hint"><i class="fas fa-inbox me-1"></i>Leads switched off wait in <strong>Unassigned</strong> for you.</div>
            </div>
            <div class="lassign__manual js-lead-assign-manual" @unless($assignSetting->isManual()) hidden @endunless>
                <span class="lassign__manual-icon" aria-hidden="true"><i class="fas fa-inbox"></i></span>
                <div>
                    <div class="fw-semibold">Every new lead waits in Unassigned</div>
                    <div class="small text-muted">Assign each one to an agent from <strong>Leads</strong>, or share them all out at once with <strong>Distribute unassigned</strong> (<i class="fas fa-shuffle"></i>).</div>
                </div>
            </div>
        </div>
    </div>

    <div class="lassign__foot">
        <button type="button" class="btn btn-sm portal-btn-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-portal-primary js-lead-assign-save" disabled><i class="fas fa-check me-1"></i>Save changes</button>
    </div>
</form>
</div>
</div>
</div>

@once
@push('styles')
<style>
    /* Lead assignment (portal/agents/_lead_assignment) */
    .lassign { padding: 0; overflow: hidden; }
    /* Slim summary bar on My Agents — opens the modal */
    .lassign-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 10px 16px; }
    .lassign-bar__icon { display: grid; place-items: center; flex-shrink: 0; width: 32px; height: 32px; border-radius: 9px; color: #fff; font-size: .85rem; background: linear-gradient(135deg, #244373, #3a5a85); }
    .lassign-bar__title { font-weight: 700; font-size: .9rem; color: var(--portal-primary-dark); }
    .lassign-bar__chips { display: flex; flex-wrap: wrap; gap: 6px; flex: 1 1 auto; min-width: 0; }
    .lassign-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; background: #eef1f8; color: var(--portal-primary); }
    .lassign-chip--mode { background: var(--portal-primary); color: #fff; }
    .lassign-chip--off { background: #f3f4f8; color: #9aa0b8; text-decoration: line-through; }
    .lassign-xbtn-state { margin-left: 4px; padding: 1px 7px; border-radius: 999px; font-size: .68rem; background: #eef1f8; color: var(--portal-primary); }
    .lassign__head { display: flex; align-items: flex-start; gap: 14px; padding: 18px 22px; border-bottom: 1px solid var(--portal-border); }
    .lassign__head > .flex-grow-1 { flex: 1 1 0; align-self: center; }
    .lassign__head .btn-close { flex-shrink: 0; margin: 2px 0 0 4px; }
    .lassign__icon { display: grid; place-items: center; flex-shrink: 0; width: 44px; height: 44px; border-radius: 12px; color: #fff; background: linear-gradient(135deg, #244373, #3a5a85); }
    .lassign__title { font-weight: 700; color: var(--portal-primary-dark); }
    .lassign__sub { font-size: .82rem; color: var(--portal-muted); }
    .lassign__foot .btn-portal-primary:disabled { opacity: .45; }
    .lassign__body { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); }
    .lassign__col { min-width: 0; padding: 18px 22px; }
    .lassign__col + .lassign__col { border-left: 1px solid var(--portal-border); background: var(--portal-bg); }
    .lassign__label { margin-bottom: 10px; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--portal-muted); }
    .lassign__foot { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 22px; border-top: 1px solid var(--portal-border); }
    /* Mode tiles */
    .lassign__modes { display: grid; gap: 10px; }
    .lassign__mode { position: relative; margin: 0; cursor: pointer; }
    .lassign__mode input { position: absolute; opacity: 0; pointer-events: none; }
    .lassign__mode-box { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid var(--portal-border); border-radius: 12px; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .lassign__mode:hover .lassign__mode-box { border-color: #c5cfe0; }
    .lassign__mode input:checked + .lassign__mode-box { border-color: var(--portal-primary); box-shadow: 0 0 0 3px rgba(36, 67, 115, .1); }
    .lassign__mode input:focus-visible + .lassign__mode-box { box-shadow: 0 0 0 3px rgba(36, 67, 115, .3); }
    .lassign__mode-icon { display: grid; place-items: center; flex-shrink: 0; width: 36px; height: 36px; border-radius: 10px; background: #eef1f8; color: var(--portal-primary); transition: background .15s, color .15s; }
    .lassign__mode input:checked + .lassign__mode-box .lassign__mode-icon { background: var(--portal-primary); color: #fff; }
    .lassign__mode-title { display: block; font-weight: 600; color: var(--portal-primary-dark); }
    .lassign__mode-text { display: block; font-size: .78rem; color: var(--portal-muted); }
    .lassign__tick { display: grid; place-items: center; flex-shrink: 0; width: 22px; height: 22px; margin-left: auto; border-radius: 50%; border: 2px solid #c5cfdf; color: transparent; font-size: .62rem; transition: all .15s; }
    .lassign__mode input:checked + .lassign__mode-box .lassign__tick { background: var(--portal-primary); border-color: var(--portal-primary); color: #fff; }
    /* Lead-type switches */
    .lassign__sources { display: grid; gap: 8px; }
    .lassign__source { display: flex; align-items: center; gap: 12px; margin: 0; padding: 10px 14px; border: 1px solid var(--portal-border); border-radius: 12px; background: #fff; cursor: pointer; transition: opacity .15s; }
    .lassign__source:has(input:not(:checked)) { opacity: .6; }
    .lassign__source .form-check-input { width: 2.4em; height: 1.3em; margin: 0; cursor: pointer; }
    .lassign__source .form-check-input:checked { background-color: var(--portal-primary); border-color: var(--portal-primary); }
    .lassign__source-icon { display: grid; place-items: center; flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; font-size: .9rem; }
    .lassign__source-icon--property { background: #fdecef; color: var(--portal-accent); }
    .lassign__source-icon--generic { background: #fff4e0; color: #b86e00; }
    .lassign__source-icon--facebook { background: #e7f0fe; color: #1877f2; }
    .lassign__source-title { display: block; font-size: .88rem; font-weight: 600; color: var(--portal-primary-dark); }
    .lassign__source-text { display: block; font-size: .76rem; color: var(--portal-muted); }
    .lassign__hint { margin-top: 10px; font-size: .78rem; color: var(--portal-muted); }
    .lassign__hint.is-warn { color: #b8283a; font-weight: 600; }
    .lassign__manual { display: flex; gap: 12px; align-items: flex-start; padding: 14px; border: 1px dashed #c9d3e6; border-radius: 12px; background: #fff; }
    .lassign__manual[hidden], .js-lead-assign-auto[hidden] { display: none; }
    .lassign__manual-icon { display: grid; place-items: center; flex-shrink: 0; width: 36px; height: 36px; border-radius: 10px; background: #fdecef; color: var(--portal-accent); }
    @media (max-width: 767.98px) {
        .lassign__body { grid-template-columns: 1fr; }
        .lassign__col + .lassign__col { border-left: 0; border-top: 1px solid var(--portal-border); }
    }
    @media (max-width: 575.98px) {
        .lassign__head, .lassign__col { padding: 14px 16px; }
    }
</style>
@endpush

@push('scripts')
<script>
    // Lead assignment: lead types only apply in automatic mode; Save lights up once something changed,
    // and is held back when automatic has every lead type switched off (the server refuses that too).
    document.querySelectorAll('.js-lead-assign').forEach(function (form) {
        var hint = form.querySelector('.js-lead-assign-hint'), hintText = hint.innerHTML;
        function state() { return new URLSearchParams(new FormData(form)).toString(); }
        var initial = state();
        function refresh() {
            var manual = form.querySelector('.js-lead-assign-mode[value="manual"]').checked;
            var invalid = !manual && !form.querySelector('input[name="sources[]"]:checked');
            form.querySelector('.js-lead-assign-auto').hidden = manual;
            form.querySelector('.js-lead-assign-manual').hidden = !manual;
            hint.classList.toggle('is-warn', invalid);
            hint.innerHTML = invalid ? '<i class="fas fa-triangle-exclamation me-1"></i>Switch on at least one lead type, or choose Manual.' : hintText;
            form.querySelectorAll('.js-lead-assign-save').forEach(function (b) { b.disabled = invalid || state() === initial; });
        }
        form.addEventListener('change', refresh);
        // Closing the modal without saving puts the choices back.
        var modal = form.closest('.modal');
        if (modal) modal.addEventListener('hidden.bs.modal', function () { form.reset(); refresh(); });
        refresh();
    });
</script>
@endpush
@endonce
