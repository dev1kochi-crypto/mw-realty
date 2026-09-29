{{--
    Tags / Stages popup on the Leads listing (toolbar buttons + the selection bar). One modal,
    two modes. Lists the viewer's own master data from LeadController::masterOptions — searched
    and paged on the server, 20 at a time, loading more on scroll — lets you add a new tag /
    stage inline, and applies to the leads ticked in the table:
      stages → exactly one stage for every selected lead   (POST leads.bulk-stage)
      tags   → any number of tags added to every lead       (POST leads.bulk-tags)
--}}
<div class="modal fade" id="leadMasterPickerModal" tabindex="-1" aria-labelledby="leadMasterPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content portal-picker">
            <div class="portal-picker-head">
                <h5 class="modal-title" id="leadMasterPickerTitle">Assign Stage</h5>
                <button type="button" class="portal-picker-new" id="leadMasterPickerNewToggle"><i class="fas fa-plus"></i> <span>Add New Stage</span></button>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="portal-picker-sub" id="leadMasterPickerHint"></div>

            <form class="portal-picker-create d-none" id="leadMasterPickerCreate" novalidate>
                <input type="color" name="color" value="#14b8a6" class="form-control form-control-color" title="Colour" aria-label="Colour">
                <input type="text" name="name" class="form-control form-control-sm" maxlength="100" placeholder="New stage name" required aria-label="Name">
                <button type="submit" class="btn btn-portal-primary btn-sm text-nowrap">Add</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="leadMasterPickerNewCancel">Cancel</button>
            </form>

            <div class="portal-picker-search">
                <input type="search" class="form-control" id="leadMasterPickerSearch" placeholder="Search Stages" autocomplete="off" aria-label="Search">
                <span class="portal-picker-search-btn" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
            </div>

            <div class="portal-picker-list" id="leadMasterPickerList" role="listbox"></div>
            <div class="portal-picker-status" id="leadMasterPickerStatus"></div>

            <div class="portal-picker-foot">
                <span class="portal-picker-chosen" id="leadMasterPickerChosen"></span>
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-portal-primary btn-sm px-3" id="leadMasterPickerApply" disabled>Apply (0)</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('leadMasterPickerModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const title = document.getElementById('leadMasterPickerTitle');
    const hint = document.getElementById('leadMasterPickerHint');
    const search = document.getElementById('leadMasterPickerSearch');
    const createForm = document.getElementById('leadMasterPickerCreate');
    const nameInput = createForm.querySelector('[name="name"]');
    const colorInput = createForm.querySelector('[name="color"]');
    const list = document.getElementById('leadMasterPickerList');
    const status = document.getElementById('leadMasterPickerStatus');
    const chosenLabel = document.getElementById('leadMasterPickerChosen');
    const applyBtn = document.getElementById('leadMasterPickerApply');
    const csrf = @json(csrf_token());
    const urls = {
        options: @json(url('portal/crm/leads/options')),
        create: { stages: @json(route('portal.crm.master.stages.store')), tags: @json(route('portal.crm.master.tags.store')) },
        apply: { stages: @json(route('portal.crm.leads.bulk-stage')), tags: @json(route('portal.crm.leads.bulk-tags')) },
    };

    let mode = 'tags';
    let nextPage = 1;
    let loading = false;
    let requestSeq = 0;
    let chosen = new Map(); // id → name

    // Current listing selection — ticked leads or all matching (index.blade.php, getLeadSelection).
    const selectedCount = function () { return window.getLeadSelection ? window.getLeadSelection().count : 0; };

    function refreshFooter() {
        const leads = selectedCount();
        hint.innerHTML = '';
        const strong = document.createElement('strong');
        strong.textContent = leads + (leads === 1 ? ' lead' : ' leads');
        hint.append(mode === 'stages' ? 'Choose one stage for ' : 'Choose tags to add to ', strong, ' selected');
        chosenLabel.textContent = Array.from(chosen.values()).join(', ');
        applyBtn.disabled = !leads || !chosen.size;
        applyBtn.textContent = 'Apply (' + chosen.size + ')';
    }

    // "Active Buyer" → "AB", "Hot" → "H".
    function initials(name) {
        const words = String(name).trim().split(/\s+/).filter(Boolean);
        return ((words[0] || '?').charAt(0) + (words.length > 1 ? words[words.length - 1].charAt(0) : '')).toUpperCase();
    }

    function renderItem(item, prepend) {
        const type = mode === 'stages' ? 'radio' : 'checkbox';
        const label = document.createElement('label');
        label.className = 'portal-picker-item';
        label.innerHTML = '<span class="portal-picker-initials"></span>'
            + '<span class="portal-picker-text"><span class="portal-picker-name"></span><span class="portal-picker-count"></span></span>'
            + '<input type="' + type + '" name="pickerChoice" class="form-check-input">';
        const input = label.querySelector('input');
        input.value = item.id;
        input.checked = chosen.has(String(item.id));
        const badge = label.querySelector('.portal-picker-initials');
        badge.textContent = initials(item.name);
        badge.style.setProperty('--item', item.color || '#ca2844');
        label.querySelector('.portal-picker-name').textContent = item.name;
        label.querySelector('.portal-picker-count').textContent = (item.leads || 0) + (item.leads === 1 ? ' lead' : ' leads');
        input.addEventListener('change', function () {
            if (mode === 'stages') chosen.clear();
            input.checked ? chosen.set(String(item.id), item.name) : chosen.delete(String(item.id));
            refreshFooter();
        });
        prepend ? list.prepend(label) : list.appendChild(label);
    }

    function load(reset) {
        if (reset) { nextPage = 1; list.innerHTML = ''; }
        if (loading || !nextPage) return;
        loading = true;
        const seq = ++requestSeq;
        status.textContent = 'Loading…';
        const params = new URLSearchParams({ page: nextPage, search: search.value.trim() });
        fetch(urls.options + '/' + mode + '?' + params, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
            .then(function (data) {
                if (seq !== requestSeq) return; // a newer search replaced this one
                data.data.forEach(function (item) { renderItem(item); });
                nextPage = data.next_page;
                status.textContent = !list.children.length
                    ? (search.value.trim() ? 'Nothing matches “' + search.value.trim() + '” — use “+ Add New” to create it.' : 'Nothing here yet — use “+ Add New” to create one.')
                    : (nextPage ? '' : data.total + ' in total');
            })
            .catch(function () { if (seq === requestSeq) status.textContent = 'Could not load the list. Try again.'; })
            .finally(function () { if (seq === requestSeq) loading = false; });
    }

    // Load the next page when the list is scrolled near its end.
    list.addEventListener('scroll', function () {
        if (list.scrollTop + list.clientHeight >= list.scrollHeight - 60) load(false);
    });

    let searchTimer;
    search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loading = false; load(true); }, 250);
    });

    window.openLeadMasterPicker = function (which) {
        if (!selectedCount()) {
            window.portalToast('info', 'Select leads in the table first.');
            return;
        }
        mode = which === 'stages' ? 'stages' : 'tags';
        chosen = new Map();
        title.textContent = mode === 'stages' ? 'Assign Stage' : 'Assign Tag';
        newToggle.querySelector('span').textContent = mode === 'stages' ? 'Add New Stage' : 'Add New Tag';
        list.setAttribute('aria-multiselectable', mode === 'tags' ? 'true' : 'false');
        search.value = '';
        search.placeholder = mode === 'stages' ? 'Search Stages' : 'Search Tags';
        nameInput.placeholder = mode === 'stages' ? 'New stage name' : 'New tag name';
        colorInput.value = mode === 'stages' ? '#4f46e5' : '#14b8a6';
        showCreate(false);
        refreshFooter();
        loading = false;
        load(true);
        modal.show();
    };
    modalEl.addEventListener('shown.bs.modal', function () { search.focus(); });

    // "+ Add New Stage / Tag" reveals the inline create row.
    const newToggle = document.getElementById('leadMasterPickerNewToggle');
    function showCreate(on) {
        createForm.classList.toggle('d-none', !on);
        newToggle.classList.toggle('active', on);
        if (on) nameInput.focus();
    }
    newToggle.addEventListener('click', function () { showCreate(createForm.classList.contains('d-none')); });
    document.getElementById('leadMasterPickerNewCancel').addEventListener('click', function () { nameInput.value = ''; showCreate(false); });

    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-open-master]');
        if (trigger) window.openLeadMasterPicker(trigger.dataset.openMaster);
    });

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Something went wrong.');
                return data;
            });
        });
    }

    // Add a tag / stage inline — it appears at the top, already chosen.
    createForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const name = nameInput.value.trim();
        if (!name) { nameInput.focus(); return; }
        const btn = createForm.querySelector('[type=submit]');
        btn.disabled = true;
        post(urls.create[mode], new URLSearchParams({ name: name, color: colorInput.value }))
            .then(function (data) {
                const item = data.stage || data.tag;
                item.leads = 0;
                if (mode === 'stages') {
                    chosen.clear();
                    list.querySelectorAll('input').forEach(function (input) { input.checked = false; });
                    document.dispatchEvent(new CustomEvent('stage:created', { detail: { id: item.id, name: item.name } }));
                } else {
                    document.dispatchEvent(new CustomEvent('tag:created', { detail: item }));
                }
                chosen.set(String(item.id), item.name);
                renderItem(item, true);
                list.scrollTop = 0;
                nameInput.value = '';
                showCreate(false);
                status.textContent = '';
                refreshFooter();
                window.portalToast('success', (mode === 'stages' ? 'Stage' : 'Tag') + ' “' + item.name + '” added.');
            })
            .catch(function (err) { window.portalToast('danger', err.message); })
            .finally(function () { btn.disabled = false; });
    });

    applyBtn.addEventListener('click', function () {
        const leads = selectedCount();
        if (!leads || !chosen.size) return;
        const body = new URLSearchParams();
        window.appendLeadSelection(body);
        if (mode === 'stages') {
            body.append('stage_id', Array.from(chosen.keys())[0]);
        } else {
            chosen.forEach(function (_, id) { body.append('tags[]', id); });
        }
        applyBtn.disabled = true;
        applyBtn.classList.add('is-busy');
        post(urls.apply[mode], body)
            .then(function (data) {
                modal.hide();
                window.portalToast('success', data.message || 'Updated.');
                if (window.reloadLeadsListing) window.reloadLeadsListing();
            })
            .catch(function (err) { window.portalToast('danger', err.message); refreshFooter(); })
            .finally(function () { applyBtn.classList.remove('is-busy'); });
    });
});
</script>
@endpush
