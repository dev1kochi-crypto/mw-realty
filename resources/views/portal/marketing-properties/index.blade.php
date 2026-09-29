@extends('portal.layouts.app')

@section('title', 'Marketing Properties')

{{--
    Super Admin › Listings › Marketing Properties. Left: choose agencies / agents (multi-select),
    then add their listings. Right: the list itself — drag (or ↑ / ↓) to reorder, first
    {{ $homeLimit }} show on the home "Realty Property" section, all on /marketing-properties.
    Every list is searched and paged on the server (PortalMarketingPropertyController).
--}}
@section('content')
<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
    <div>
        <div class="portal-section-title mb-1">Marketing Properties</div>
        <p class="portal-muted small mb-0">Pick listings from any agency or agent for the home page <strong>Realty Property</strong> section. The first {{ $homeLimit }} show on the home page; all of them on the “View more” page.</p>
    </div>
    <a href="{{ url('/marketing-properties') }}" target="_blank" rel="noopener" class="portal-xbtn" aria-label="View on website"><i class="fas fa-arrow-up-right-from-square"></i><span>View on website</span></a>
</div>

<div class="row g-3">
    {{-- ========== Add ========== --}}
    <div class="col-xl-6">
        <section class="portal-lp-card mb-3">
            <header class="portal-lp-card-head">
                <h2><span class="portal-mk-step">1</span> Agencies &amp; agents</h2>
                <button type="button" class="btn btn-link btn-sm p-0 d-none" id="mkAccountsClear">Clear</button>
            </header>
            <div class="portal-lp-card-body">
                <div class="d-flex flex-wrap gap-1 mb-2" id="mkAccountChips"><span class="portal-muted small">Choose one or more below.</span></div>
                <div class="portal-picker-search m-0 mb-2">
                    <input type="search" class="form-control" id="mkAccountSearch" placeholder="Search agencies or agents" autocomplete="off" aria-label="Search agencies or agents">
                    <span class="portal-picker-search-btn" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                </div>
                <div class="portal-mk-scroll" id="mkAccountList" role="listbox" aria-multiselectable="true"></div>
                <div class="portal-picker-status" id="mkAccountStatus"></div>
            </div>
        </section>

        <section class="portal-lp-card">
            <header class="portal-lp-card-head">
                <h2><span class="portal-mk-step">2</span> Their properties <span class="portal-lp-count d-none" id="mkPropertyTotal"></span></h2>
                <button type="button" class="btn btn-portal-primary btn-sm" id="mkAddSelected" disabled><i class="fas fa-plus me-1"></i> Add selected (0)</button>
            </header>
            <div class="portal-lp-card-body">
                <div class="portal-picker-search m-0 mb-2">
                    <input type="search" class="form-control" id="mkPropertySearch" placeholder="Search by title or reference" autocomplete="off" aria-label="Search properties" disabled>
                    <span class="portal-picker-search-btn" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                </div>
                <div class="portal-mk-scroll" id="mkPropertyList"></div>
                <div class="portal-picker-status" id="mkPropertyStatus">Choose an agency or agent above to see their listings.</div>
            </div>
        </section>
    </div>

    {{-- ========== The list ========== --}}
    <div class="col-xl-6">
        <section class="portal-lp-card">
            <header class="portal-lp-card-head">
                <h2>Marketing list <span class="portal-lp-count" id="mkListCount">{{ $items->count() }}</span></h2>
                <span class="portal-muted small"><i class="fas fa-grip-vertical me-1"></i>Drag to reorder</span>
            </header>
            <div class="portal-lp-card-body p-0">
                <ol class="portal-mk-list" id="mkList" aria-label="Marketing properties, in order"></ol>
                <div class="portal-lp-empty py-4 d-none" id="mkListEmpty">
                    Nothing picked yet — the home page shows the latest listings automatically until you add some.
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = @json(csrf_token());
    const homeLimit = {{ (int) $homeLimit }};
    const urls = {
        accounts: @json(route('portal.marketing.accounts')),
        properties: @json(route('portal.marketing.properties')),
        store: @json(route('portal.marketing.store')),
        reorder: @json(route('portal.marketing.reorder')),
        destroy: @json(url('portal/marketing-properties')),
    };
    let items = @json($items->values());

    const $ = function (id) { return document.getElementById(id); };
    const el = function (tag, cls, text) {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    };
    function thumb(src) {
        const box = el('span', 'portal-mk-thumb');
        if (src) { const img = el('img'); img.src = src; img.alt = ''; img.loading = 'lazy'; box.appendChild(img); }
        else box.innerHTML = '<i class="fas fa-image"></i>';
        return box;
    }
    function send(url, method, body) {
        return fetch(url, {
            method: method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                if (!r.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Something went wrong.');
                return data;
            });
        });
    }

    /* Server-paged list with search + load-on-scroll, reused for accounts and properties. */
    function pagedList(opts) {
        let next = 1, loading = false, seq = 0;
        function load(reset) {
            if (reset) { next = 1; opts.list.innerHTML = ''; }
            if (loading || !next || !opts.ready()) return;
            loading = true;
            const mine = ++seq;
            opts.status.textContent = 'Loading…';
            const params = opts.params();
            params.append('page', next);
            params.append('search', opts.search.value.trim());
            fetch(opts.url + '?' + params, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
                .then(function (data) {
                    if (mine !== seq) return;
                    data.data.forEach(function (row) { opts.list.appendChild(opts.render(row)); });
                    next = data.next_page;
                    if (opts.onPage) opts.onPage(data);
                    opts.status.textContent = opts.list.children.length ? '' : (opts.search.value.trim() ? 'Nothing matches your search.' : opts.empty);
                })
                .catch(function () { if (mine === seq) opts.status.textContent = 'Could not load. Try again.'; })
                .finally(function () { if (mine === seq) loading = false; });
        }
        opts.list.addEventListener('scroll', function () {
            if (opts.list.scrollTop + opts.list.clientHeight >= opts.list.scrollHeight - 60) load(false);
        });
        let timer;
        opts.search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { loading = false; load(true); }, 250);
        });
        return { reload: function () { loading = false; load(true); } };
    }

    /* ---------- 1. Accounts (multi-select) ---------- */
    const chosenAccounts = new Map(); // id → name
    function renderChips() {
        const chips = $('mkAccountChips');
        chips.innerHTML = '';
        $('mkAccountsClear').classList.toggle('d-none', !chosenAccounts.size);
        if (!chosenAccounts.size) { chips.appendChild(el('span', 'portal-muted small', 'Choose one or more below.')); return; }
        chosenAccounts.forEach(function (name, id) {
            const chip = el('span', 'portal-mk-chip', name);
            const x = el('button', '');
            x.type = 'button';
            x.setAttribute('aria-label', 'Remove ' + name);
            x.innerHTML = '<i class="fas fa-xmark"></i>';
            x.addEventListener('click', function () { toggleAccount(id, name, false); });
            chip.appendChild(x);
            chips.appendChild(chip);
        });
    }
    function toggleAccount(id, name, on) {
        on ? chosenAccounts.set(String(id), name) : chosenAccounts.delete(String(id));
        const box = $('mkAccountList').querySelector('input[value="' + id + '"]');
        if (box) box.checked = on;
        renderChips();
        chosenProperties.clear();
        refreshAddButton();
        $('mkPropertySearch').disabled = !chosenAccounts.size;
        properties.reload();
        if (!chosenAccounts.size) {
            $('mkPropertyList').innerHTML = '';
            $('mkPropertyTotal').classList.add('d-none');
            $('mkPropertyStatus').textContent = 'Choose an agency or agent above to see their listings.';
        }
    }
    const accounts = pagedList({
        url: urls.accounts, list: $('mkAccountList'), status: $('mkAccountStatus'), search: $('mkAccountSearch'),
        empty: 'No agencies or agents yet.', ready: function () { return true; }, params: function () { return new URLSearchParams(); },
        render: function (row) {
            const label = el('label', 'portal-picker-item');
            const initials = el('span', 'portal-picker-initials', row.name.split(/\s+/).filter(Boolean).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase());
            initials.style.setProperty('--item', row.type === 'Agency' ? '#244373' : '#ca2844');
            const text = el('span', 'portal-picker-text');
            text.append(el('span', 'portal-picker-name', row.name), el('span', 'portal-picker-count', row.type + ' · ' + row.listings + (row.listings === 1 ? ' listing' : ' listings')));
            const input = el('input', 'form-check-input');
            input.type = 'checkbox';
            input.value = row.id;
            input.checked = chosenAccounts.has(String(row.id));
            input.addEventListener('change', function () { toggleAccount(row.id, row.name, input.checked); });
            label.append(initials, text, input);
            return label;
        },
    });
    $('mkAccountsClear').addEventListener('click', function () {
        chosenAccounts.clear();
        $('mkAccountList').querySelectorAll('input').forEach(function (i) { i.checked = false; });
        toggleAccount(0, '', false);
    });

    /* ---------- 2. Their properties ---------- */
    const chosenProperties = new Set();
    function refreshAddButton() {
        $('mkAddSelected').disabled = !chosenProperties.size;
        $('mkAddSelected').innerHTML = '<i class="fas fa-plus me-1"></i> Add selected (' + chosenProperties.size + ')';
    }
    const properties = pagedList({
        url: urls.properties, list: $('mkPropertyList'), status: $('mkPropertyStatus'), search: $('mkPropertySearch'),
        empty: 'These accounts have no active listings.',
        ready: function () { return chosenAccounts.size > 0; },
        params: function () {
            const p = new URLSearchParams();
            chosenAccounts.forEach(function (_, id) { p.append('accounts[]', id); });
            return p;
        },
        onPage: function (data) {
            $('mkPropertyTotal').textContent = data.total;
            $('mkPropertyTotal').classList.remove('d-none');
        },
        render: function (row) {
            const onList = row.in_list || items.some(function (i) { return i.id === row.id; });
            const label = el('label', 'portal-picker-item portal-mk-prop' + (onList ? ' is-on-list' : ''));
            const text = el('span', 'portal-picker-text');
            text.append(el('span', 'portal-picker-name', row.title), el('span', 'portal-picker-count', [row.reference, row.owner, row.price].filter(Boolean).join(' · ')));
            label.append(thumb(row.image), text);
            if (onList) {
                label.appendChild(el('span', 'portal-mk-onlist', 'On list'));
            } else {
                const input = el('input', 'form-check-input');
                input.type = 'checkbox';
                input.value = row.id;
                input.checked = chosenProperties.has(row.id);
                input.addEventListener('change', function () {
                    input.checked ? chosenProperties.add(row.id) : chosenProperties.delete(row.id);
                    refreshAddButton();
                });
                label.appendChild(input);
            }
            return label;
        },
    });

    $('mkAddSelected').addEventListener('click', function () {
        const btn = this;
        const body = new URLSearchParams();
        chosenProperties.forEach(function (id) { body.append('property_ids[]', id); });
        btn.classList.add('is-busy');
        btn.disabled = true;
        send(urls.store, 'POST', body)
            .then(function (data) {
                const addedIds = Array.from(chosenProperties);
                items = data.items;
                chosenProperties.clear();
                renderList(addedIds); // new rows land at the top and flash
                properties.reload();
                window.portalToast('success', data.message);
            })
            .catch(function (err) { window.portalToast('danger', err.message); })
            .finally(function () { btn.classList.remove('is-busy'); refreshAddButton(); });
    });

    /* ---------- The list: render, drag / ↑↓ reorder, remove ---------- */
    const list = $('mkList');
    function renderList(highlightIds) {
        list.innerHTML = '';
        $('mkListCount').textContent = items.length;
        $('mkListEmpty').classList.toggle('d-none', items.length > 0);
        items.forEach(function (item, index) {
            if (index === homeLimit) {
                const divider = el('li', 'portal-mk-divider', 'Above this line: shown on the home page · below: only on the “View more” page');
                divider.setAttribute('aria-hidden', 'true');
                list.appendChild(divider);
            }
            const row = el('li', 'portal-mk-row' + (index < homeLimit ? ' is-home' : ''));
            row.draggable = true;
            row.dataset.id = item.id;
            const handle = el('span', 'portal-mk-handle');
            handle.innerHTML = '<i class="fas fa-grip-vertical"></i>';
            const pos = el('span', 'portal-mk-pos', index + 1);
            const text = el('span', 'portal-picker-text');
            text.append(el('span', 'portal-picker-name', item.title), el('span', 'portal-picker-count', [item.reference, item.owner, item.price].filter(Boolean).join(' · ')));
            const actions = el('span', 'portal-mk-actions');
            [['up', 'fa-arrow-up', 'Move up', index === 0], ['down', 'fa-arrow-down', 'Move down', index === items.length - 1]].forEach(function (a) {
                const b = el('button', '');
                b.type = 'button';
                b.title = a[2];
                b.setAttribute('aria-label', a[2] + ': ' + item.title);
                b.disabled = a[3];
                b.innerHTML = '<i class="fas ' + a[1] + '"></i>';
                b.addEventListener('click', function () { move(index, a[0] === 'up' ? index - 1 : index + 1); });
                actions.appendChild(b);
            });
            const remove = el('button', 'text-danger');
            remove.type = 'button';
            remove.title = 'Remove';
            remove.setAttribute('aria-label', 'Remove ' + item.title);
            remove.innerHTML = '<i class="fas fa-trash-can"></i>';
            remove.addEventListener('click', function () { removeItem(item); });
            actions.appendChild(remove);
            row.append(handle, pos, thumb(item.image), text, actions);
            if ((highlightIds || []).includes(item.id)) row.classList.add('portal-lp-just-changed');
            list.appendChild(row);
        });
    }

    function saveOrder() {
        const body = new URLSearchParams();
        items.forEach(function (i) { body.append('property_ids[]', i.id); });
        send(urls.reorder, 'POST', body)
            .then(function (data) { window.portalToast('success', data.message || 'Order saved.'); })
            .catch(function (err) { window.portalToast('danger', err.message); });
    }

    function move(from, to) {
        if (to < 0 || to >= items.length || from === to) return;
        const [moved] = items.splice(from, 1);
        items.splice(to, 0, moved);
        renderList([moved.id]);
        saveOrder();
    }

    function removeItem(item) {
        const ask = window.portalConfirm
            ? window.portalConfirm({ title: 'Remove from the list?', message: '“' + item.title + '” will no longer show in Realty Property.', confirmText: 'Remove', tone: 'danger' })
            : Promise.resolve(confirm('Remove “' + item.title + '” from the list?'));
        ask.then(function (ok) {
            if (!ok) return;
            send(urls.destroy + '/' + item.id, 'DELETE')
                .then(function (data) {
                    items = data.items;
                    renderList();
                    properties.reload();
                    window.portalToast('success', data.message);
                })
                .catch(function (err) { window.portalToast('danger', err.message); });
        });
    }

    // Native drag & drop.
    let dragId = null;
    list.addEventListener('dragstart', function (e) {
        const row = e.target.closest('.portal-mk-row');
        if (!row) return;
        dragId = Number(row.dataset.id);
        row.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.id);
    });
    list.addEventListener('dragover', function (e) {
        const row = e.target.closest('.portal-mk-row');
        if (!row || dragId === null) return;
        e.preventDefault();
        list.querySelectorAll('.is-drop-before, .is-drop-after').forEach(function (r) { r.classList.remove('is-drop-before', 'is-drop-after'); });
        const box = row.getBoundingClientRect();
        row.classList.add(e.clientY < box.top + box.height / 2 ? 'is-drop-before' : 'is-drop-after');
    });
    list.addEventListener('drop', function (e) {
        const row = e.target.closest('.portal-mk-row');
        if (!row || dragId === null) return;
        e.preventDefault();
        const from = items.findIndex(function (i) { return i.id === dragId; });
        let to = items.findIndex(function (i) { return i.id === Number(row.dataset.id); });
        if (row.classList.contains('is-drop-after')) to += 1;
        if (from < to) to -= 1;
        move(from, to);
    });
    list.addEventListener('dragend', function () {
        dragId = null;
        list.querySelectorAll('.is-dragging, .is-drop-before, .is-drop-after').forEach(function (r) { r.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after'); });
    });

    renderList();
    accounts.reload();
});
</script>
@endpush
