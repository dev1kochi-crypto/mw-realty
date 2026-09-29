{{--
    "N leads" popup for Master › Stage / Tag / Source. Opened by any .linked-leads-btn
    (data-type = stages|tags|sources, data-id, data-name). Lists the leads using the item — 20 a page
    from the server, more on scroll, searchable — and takes it off the ticked ones or all of them
    (MasterLinkedLeadsController), so the item can then be deleted. The page reloads on close if
    anything changed, to refresh the counts and the Delete buttons.
--}}
@push('styles')
<style>
    .ll-list { max-height: 360px; overflow-y: auto; border: 1px solid var(--portal-border); border-radius: 12px; }
    .ll-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.9rem; border-bottom: 1px solid var(--portal-border); font-size: 0.86rem; }
    .ll-row:last-child { border-bottom: 0; }
    .ll-row a { text-decoration: none; font-weight: 700; }
    .ll-row small { color: var(--portal-muted); }
    .ll-bar { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; font-size: 0.84rem; margin-bottom: 0.6rem; }
    .ll-status { padding: 1rem; text-align: center; font-size: 0.84rem; color: var(--portal-muted); }
    .linked-leads-btn { font-weight: 700; }
</style>
@endpush

<div class="modal fade" id="linkedLeadsModal" tabindex="-1" aria-labelledby="linkedLeadsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="linkedLeadsTitle">Leads using this</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small py-2 d-none" id="llInUse"><i class="fas fa-info-circle me-1"></i>It can't be deleted while leads use it. Remove it from these leads first, then delete.</div>
                <input type="search" class="form-control form-control-sm mb-2" id="llSearch" placeholder="Search these leads by name, email or phone" autocomplete="off">
                <div class="ll-bar">
                    <label class="form-check mb-0">
                        <input type="checkbox" class="form-check-input" id="llPageAll">
                        <span class="form-check-label">Select all shown</span>
                    </label>
                    <span id="llSelectedInfo" class="portal-muted"></span>
                    <button type="button" class="btn btn-link btn-sm p-0" id="llSelectEvery">Select all <span id="llTotal">0</span> leads</button>
                </div>
                <div class="ll-list" id="llList"></div>
                <div class="alert alert-danger small mt-2 mb-0 d-none" id="llError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="llRemove" disabled><i class="fas fa-unlink me-1"></i>Remove from selected</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('linkedLeadsModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    const list = document.getElementById('llList'), search = document.getElementById('llSearch');
    const pageAll = document.getElementById('llPageAll'), every = document.getElementById('llSelectEvery');
    const info = document.getElementById('llSelectedInfo'), removeBtn = document.getElementById('llRemove');
    const errorBox = document.getElementById('llError');
    const base = @json(url('portal/crm/master'));
    const headers = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' };
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let type = null, id = null, label = '', page = 1, more = false, loading = false, term = '', total = 0, allMode = false, changed = false, timer = null, reqNo = 0;

    const url = suffix => `${base}/${type}/${id}/leads${suffix || ''}`;
    const checked = () => [...list.querySelectorAll('.ll-check:checked')].map(c => Number(c.value));

    function refreshBar() {
        const n = allMode ? total : checked().length;
        info.textContent = allMode ? `All ${total} leads selected` : (n ? `${n} selected` : '');
        every.textContent = allMode ? 'Clear selection' : `Select all ${total} leads`;
        every.classList.toggle('d-none', total <= 0);
        removeBtn.disabled = n === 0;
        removeBtn.innerHTML = `<i class="fas fa-unlink me-1"></i>Remove ${label} from ${allMode ? 'all ' + total : (n || 'selected')} ${n === 1 ? 'lead' : 'leads'}`;
    }

    function load(reset) {
        if (loading && !reset) return;
        if (reset) { page = 1; list.innerHTML = ''; pageAll.checked = false; }
        loading = true;
        const mine = ++reqNo;
        list.insertAdjacentHTML('beforeend', '<div class="ll-status" data-loading>Loading…</div>');
        fetch(url(`?page=${page}&q=${encodeURIComponent(term)}`), { headers })
            .then(r => r.json())
            .then(data => {
                if (mine !== reqNo) return;
                list.querySelector('[data-loading]')?.remove();
                if (page === 1) { total = data.total; document.getElementById('llTotal').textContent = total; }
                data.results.forEach(l => list.insertAdjacentHTML('beforeend',
                    `<label class="ll-row"><input type="checkbox" class="form-check-input ll-check" value="${l.id}" ${allMode ? 'checked disabled' : ''}>
                     <span class="flex-grow-1 min-w-0"><a href="${esc(l.url)}" target="_blank">${esc(l.name)}</a><br><small>${esc(l.contact || '—')}${l.owner ? ' · ' + esc(l.owner) : ''}</small></span>
                     <small>${esc(l.date)}</small></label>`));
                if (page === 1 && !data.results.length) {
                    list.innerHTML = `<div class="ll-status">${term ? 'No leads match.' : 'No leads use this any more — you can delete it now.'}</div>`;
                }
                more = data.more; page++;
                refreshBar();
            })
            .catch(() => { list.querySelector('[data-loading]')?.remove(); list.insertAdjacentHTML('beforeend', '<div class="ll-status">Could not load leads.</div>'); })
            .finally(() => { if (mine === reqNo) loading = false; });
    }

    // Opened from a row's lead count, or from Delete when the item is still in use.
    window.openLinkedLeads = function (btn, inUse) {
        type = btn.dataset.type; id = btn.dataset.id; label = btn.dataset.label || 'it';
        term = ''; search.value = ''; allMode = false; errorBox.classList.add('d-none');
        document.getElementById('linkedLeadsTitle').textContent = `Leads using “${btn.dataset.name}”`;
        document.getElementById('llInUse').classList.toggle('d-none', !inUse);
        load(true);
        modal.show();
    };
    document.addEventListener('click', e => {
        const btn = e.target.closest('.linked-leads-btn');
        if (btn) window.openLinkedLeads(btn, false);
    });

    list.addEventListener('change', e => { if (e.target.classList.contains('ll-check')) refreshBar(); });
    list.addEventListener('scroll', () => { if (more && !loading && list.scrollTop + list.clientHeight >= list.scrollHeight - 40) load(false); });
    pageAll.addEventListener('change', () => { list.querySelectorAll('.ll-check:not(:disabled)').forEach(c => { c.checked = pageAll.checked; }); refreshBar(); });
    every.addEventListener('click', () => {
        allMode = !allMode;
        list.querySelectorAll('.ll-check').forEach(c => { c.checked = allMode; c.disabled = allMode; });
        pageAll.checked = allMode; pageAll.disabled = allMode;
        refreshBar();
    });
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { term = search.value.trim(); allMode = false; pageAll.disabled = false; load(true); }, 300); });

    removeBtn.addEventListener('click', async () => {
        const n = allMode ? total : checked().length;
        if (!n) return;
        const ok = await window.portalConfirm({ title: `Remove from ${n} ${n === 1 ? 'lead' : 'leads'}?`, message: `The ${label} is taken off ${allMode ? 'every lead listed' : 'the selected leads'}. The leads themselves are kept.`, confirmText: 'Remove', tone: 'danger' });
        if (!ok) return;
        removeBtn.disabled = true;
        errorBox.classList.add('d-none');
        fetch(url('/remove'), { method: 'POST', headers, body: JSON.stringify(allMode ? { all: true } : { ids: checked() }) })
            .then(async r => { const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.message || 'Could not remove.'); return d; })
            .then(d => { changed = true; allMode = false; pageAll.disabled = false; window.portalToast?.('success', d.message); load(true); })
            .catch(err => { errorBox.textContent = err.message; errorBox.classList.remove('d-none'); refreshBar(); });
    });

    modalEl.addEventListener('hidden.bs.modal', () => { if (changed) window.location.reload(); });
})();
</script>
@endpush
