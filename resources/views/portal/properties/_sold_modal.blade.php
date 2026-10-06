{{--
    "Mark as sold / rented" popup for the Properties / Commercial listing cards. Opened by any
    .mark-sold-property button (data-id/title/ref/thumb/price/currency/type). The buyer is an existing
    lead (paged server-side picker, 20 a request, loads more on scroll) or a new buyer saved as a lead.
    Posts to portal/properties/{id}/mark-sold — see PropertySaleService.
--}}
@push('styles')
<style>
    .sm-seg { display: flex; gap: 0.4rem; }
    .sm-seg input { display: none; }
    .sm-seg label { flex: 1; text-align: center; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--portal-border); cursor: pointer; font-weight: 700; font-size: 0.86rem; color: var(--portal-muted); }
    .sm-seg input:checked + label { background: var(--portal-primary); border-color: var(--portal-primary); color: #fff; }
    .sm-property { display: flex; gap: 0.75rem; align-items: center; padding: 0.6rem; border-radius: 12px; background: var(--portal-bg); }
    .sm-property img { width: 64px; height: 48px; object-fit: cover; border-radius: 8px; }
    .sm-combo { position: relative; }
    .sm-combo .form-control.is-picked { font-weight: 700; background: rgba(36, 67, 115, 0.06); }
    .sm-leads { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20; max-height: 240px; overflow-y: auto; border: 1px solid var(--portal-border); border-radius: 10px; background: var(--portal-surface, #fff); box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14); }
    .sm-lead { display: flex; justify-content: space-between; gap: 0.5rem; align-items: center; width: 100%; padding: 0.5rem 0.75rem; border: 0; border-bottom: 1px solid var(--portal-border); background: none; text-align: left; font-size: 0.86rem; }
    .sm-lead:hover { background: var(--portal-bg); }
    .sm-lead.is-picked { background: rgba(36, 67, 115, 0.1); }
    .sm-lead small { color: var(--portal-muted); }
    .sm-leads-status { padding: 0.6rem; text-align: center; font-size: 0.8rem; color: var(--portal-muted); }
    .sm-upload { display: flex; align-items: center; gap: 0.6rem; padding: 0.7rem 0.85rem; border: 1px dashed var(--portal-border); border-radius: 10px; cursor: pointer; margin: 0; }
    .sm-upload:hover, .sm-upload:focus-within { border-color: var(--portal-primary); }
    .sm-upload.has-file { border-style: solid; border-color: var(--portal-primary); background: rgba(36, 67, 115, 0.05); }
    .sm-upload input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .sm-upload-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.86rem; color: var(--portal-muted); }
    .sm-upload.has-file .sm-upload-name { color: inherit; font-weight: 600; }
    .sm-upload-btn { padding: 0.2rem 0.7rem; border: 1px solid var(--portal-border); border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
</style>
@endpush

<div class="modal fade" id="soldModal" tabindex="-1" aria-labelledby="soldModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" id="soldForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="soldModalTitle"><i class="fas fa-handshake me-2"></i>Mark as sold / rented</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="sm-property mb-3">
                    <img id="soldThumb" src="" alt="">
                    <div class="min-w-0">
                        <div class="fw-bold text-truncate" id="soldPropertyTitle"></div>
                        <div class="small portal-muted" id="soldPropertyRef"></div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Outcome</label>
                        <div class="sm-seg">
                            <input type="radio" name="type" id="soldTypeSold" value="sold"><label for="soldTypeSold"><i class="fas fa-key me-1"></i>Sold</label>
                            <input type="radio" name="type" id="soldTypeRented" value="rented"><label for="soldTypeRented"><i class="fas fa-file-signature me-1"></i>Rented</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="soldPrice"><span data-sold-label="price">Sold price</span> <span class="portal-muted small" id="soldCurrency"></span></label>
                        <input type="number" class="form-control" name="price" id="soldPrice" min="0" step="any" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="soldDate"><span data-sold-label="date">Sale date</span></label>
                        <input type="date" class="form-control" name="sold_at" id="soldDate" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-6 d-none" id="soldRentedUntilWrap">
                        <label class="form-label fw-semibold" for="soldRentedUntil">Lease ends <span class="portal-muted small">(optional)</span></label>
                        <input type="date" class="form-control" name="rented_until" id="soldRentedUntil">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="soldCommission">Commission <span class="portal-muted small">(optional)</span></label>
                        <input type="number" class="form-control" name="commission" id="soldCommission" min="0" step="any">
                    </div>
                </div>

                <hr class="my-3">

                <label class="form-label fw-semibold"><span data-sold-label="buyer">Buyer</span></label>
                <div class="sm-seg mb-3">
                    <input type="radio" name="buyer_mode" id="soldBuyerExisting" value="existing" checked><label for="soldBuyerExisting"><i class="fas fa-address-book me-1"></i>Existing lead</label>
                    <input type="radio" name="buyer_mode" id="soldBuyerNew" value="new"><label for="soldBuyerNew"><i class="fas fa-user-plus me-1"></i>New buyer</label>
                </div>

                <div id="soldExistingWrap">
                    <div class="sm-combo">
                        <input type="search" class="form-control" id="soldLeadSearch" placeholder="Search leads by name, email or phone" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="soldLeadList">
                        <div class="sm-leads d-none" id="soldLeadList" role="listbox"></div>
                    </div>
                    <input type="hidden" name="lead_id" id="soldLeadId">
                    <div class="form-text mt-2">Click to pick a lead — ones that enquired about this listing are shown first.</div>
                </div>

                <div id="soldNewWrap" class="d-none">
                    <div class="row g-2">
                        <div class="col-md-12"><input type="text" class="form-control" name="buyer[name]" placeholder="Full name *" maxlength="255"></div>
                        <div class="col-md-6"><input type="email" class="form-control" name="buyer[email]" placeholder="Email" maxlength="255"></div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" style="max-width: 5.5rem;" name="buyer[phone_country_code]" value="+971" maxlength="8" aria-label="Country code">
                                <input type="tel" class="form-control" name="buyer[phone]" placeholder="Phone" maxlength="30">
                            </div>
                        </div>
                    </div>
                    <div class="form-text">Saved as a new lead in your CRM, linked to this listing.</div>
                </div>

                <label class="form-label fw-semibold mt-3" for="soldNotes">Notes <span class="portal-muted small">(optional)</span></label>
                <textarea class="form-control" name="notes" id="soldNotes" rows="2" maxlength="2000" placeholder="Payment terms, handover, anything worth keeping"></textarea>

                <hr class="my-3">

                <div class="fw-semibold mb-1">Proof of the deal</div>
                <div class="form-text mt-0 mb-2">Official documents for this transaction. Kept private — only your account and MW Realty admins can open them.</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1" for="soldOwnershipDoc">Property ownership document <span class="portal-muted fw-normal">(title deed)</span></label>
                        <label class="sm-upload">
                            <i class="far fa-file-lines portal-muted"></i>
                            <span class="sm-upload-name" data-empty="Click to upload">Click to upload</span>
                            <span class="sm-upload-btn">Upload</span>
                            <input type="file" name="ownership_document" id="soldOwnershipDoc" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                        </label>
                        <div class="form-text">PDF, JPG or PNG · max 10 MB</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1" for="soldContractDoc" data-sold-label="contract">Sale contract (Form F / MOU)</label>
                        <label class="sm-upload">
                            <i class="far fa-file-lines portal-muted"></i>
                            <span class="sm-upload-name" data-empty="Click to upload">Click to upload</span>
                            <span class="sm-upload-btn">Upload</span>
                            <input type="file" name="contract_document" id="soldContractDoc" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                        </label>
                        <div class="form-text">PDF, JPG or PNG · max 10 MB</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1" for="soldDocsPassword">Document password <span class="portal-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control" name="documents_password" id="soldDocsPassword" maxlength="255" autocomplete="off" placeholder="Add a password if your files are password protected">
                    </div>
                </div>

                <div class="alert alert-info small mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>The listing is taken off the website and moved to <strong>Sold Listings</strong>. The lead is moved to your won stage. You can undo this later.</div>
                <div class="alert alert-danger small mt-3 mb-0 d-none" id="soldError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-portal-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-portal-primary btn-sm" id="soldSubmit"><i class="fas fa-check me-1"></i>Mark as sold</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('soldModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('soldForm');
    const list = document.getElementById('soldLeadList');
    const search = document.getElementById('soldLeadSearch');
    const leadId = document.getElementById('soldLeadId');
    const errorBox = document.getElementById('soldError');
    const submit = document.getElementById('soldSubmit');
    const base = "{{ url('portal/properties') }}/";
    const headers = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' };
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let propertyId = null, page = 1, more = false, loading = false, term = '', timer = null, requestNo = 0;

    function setType(type) {
        const rented = type === 'rented';
        form.querySelector(`input[name="type"][value="${type}"]`).checked = true;
        document.getElementById('soldRentedUntilWrap').classList.toggle('d-none', !rented);
        modalEl.querySelector('[data-sold-label="price"]').textContent = rented ? 'Rent (agreed)' : 'Sold price';
        modalEl.querySelector('[data-sold-label="date"]').textContent = rented ? 'Rented on' : 'Sale date';
        modalEl.querySelector('[data-sold-label="buyer"]').textContent = rented ? 'Tenant' : 'Buyer';
        modalEl.querySelector('[data-sold-label="contract"]').textContent = rented ? 'Ejari (official DLD document)' : 'Sale contract (Form F / MOU)';
        submit.innerHTML = '<i class="fas fa-check me-1"></i>' + (rented ? 'Mark as rented' : 'Mark as sold');
    }

    function setMode(mode) {
        document.getElementById('soldExistingWrap').classList.toggle('d-none', mode !== 'existing');
        document.getElementById('soldNewWrap').classList.toggle('d-none', mode !== 'new');
    }

    function loadLeads(reset) {
        if (loading && !reset) return;
        if (reset) { page = 1; list.innerHTML = ''; }
        loading = true;
        const mine = ++requestNo;
        const status = document.createElement('div');
        status.className = 'sm-leads-status';
        status.textContent = 'Loading…';
        list.appendChild(status);

        fetch(`${base}${propertyId}/sale-leads?page=${page}&q=${encodeURIComponent(term)}`, { headers })
            .then(r => r.json())
            .then(data => {
                if (mine !== requestNo) return;
                status.remove();
                data.results.forEach(lead => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'sm-lead' + (String(lead.id) === leadId.value ? ' is-picked' : '');
                    btn.dataset.id = lead.id;
                    btn.dataset.label = lead.name + (lead.contact ? ' · ' + lead.contact : '');
                    btn.innerHTML = `<span><strong>${esc(lead.name)}</strong><br><small>${esc(lead.contact || '—')}</small></span>`
                        + (lead.this_property ? '<span class="badge bg-success-subtle text-success-emphasis">Enquired on this listing</span>' : '');
                    list.appendChild(btn);
                });
                if (page === 1 && !data.results.length) {
                    list.innerHTML = '<div class="sm-leads-status">No leads found. Use “New buyer” to add them.</div>';
                }
                more = data.more;
                page++;
            })
            .catch(() => { status.textContent = 'Could not load leads.'; })
            .finally(() => { if (mine === requestNo) loading = false; });
    }

    list.addEventListener('scroll', () => {
        if (more && !loading && list.scrollTop + list.clientHeight >= list.scrollHeight - 40) loadLeads(false);
    });
    const openList = () => { list.classList.remove('d-none'); search.setAttribute('aria-expanded', 'true'); };
    const closeList = () => { list.classList.add('d-none'); search.setAttribute('aria-expanded', 'false'); };
    const clearPick = () => { leadId.value = ''; search.classList.remove('is-picked'); };

    list.addEventListener('mousedown', e => e.preventDefault()); // keep focus so the pick isn't lost to blur
    list.addEventListener('click', e => {
        const btn = e.target.closest('.sm-lead');
        if (!btn) return;
        leadId.value = btn.dataset.id;
        search.value = btn.dataset.label;
        search.classList.add('is-picked');
        list.querySelectorAll('.sm-lead').forEach(b => b.classList.toggle('is-picked', b === btn));
        closeList();
    });
    search.addEventListener('focus', () => {
        // Picked already: reopen the full list rather than one filtered to the picked label.
        if (leadId.value && term !== '') { term = ''; loadLeads(true); }
        openList();
    });
    search.addEventListener('click', openList);
    // Close on a click outside / Esc / Tab — not on blur, so switching windows keeps it open.
    document.addEventListener('pointerdown', e => { if (!e.target.closest('.sm-combo')) closeList(); });
    search.addEventListener('keydown', e => {
        if (e.key === 'Escape') { e.stopPropagation(); closeList(); }
        if (e.key === 'Tab') closeList();
    });
    search.addEventListener('input', () => {
        if (leadId.value) clearPick();
        openList();
        clearTimeout(timer);
        timer = setTimeout(() => { term = search.value.trim(); loadLeads(true); }, 300);
    });
    function showFile(input) {
        const box = input.closest('.sm-upload');
        const file = input.files[0];
        box.classList.toggle('has-file', !!file);
        box.querySelector('.sm-upload-name').textContent = file ? file.name : box.querySelector('.sm-upload-name').dataset.empty;
        box.querySelector('.sm-upload-btn').textContent = file ? 'Change' : 'Upload';
    }

    form.addEventListener('change', e => {
        if (e.target.type === 'file') showFile(e.target);
        if (e.target.name === 'type') setType(e.target.value);
        if (e.target.name === 'buyer_mode') setMode(e.target.value);
    });

    document.addEventListener('click', e => {
        const btn = e.target.closest('.mark-sold-property');
        if (!btn) return;
        form.reset();
        form.querySelectorAll('input[type="file"]').forEach(showFile);
        propertyId = btn.dataset.id;
        clearPick();
        closeList();
        term = '';
        errorBox.classList.add('d-none');
        document.getElementById('soldThumb').src = btn.dataset.thumb || 'https://placehold.co/64x48?text=%20';
        document.getElementById('soldPropertyTitle').textContent = btn.dataset.title || 'Property';
        document.getElementById('soldPropertyRef').textContent = btn.dataset.ref ? 'Ref: ' + btn.dataset.ref : '';
        document.getElementById('soldCurrency').textContent = '(' + (btn.dataset.currency || 'AED') + ')';
        document.getElementById('soldPrice').value = btn.dataset.price || '';
        document.getElementById('soldDate').value = new Date().toLocaleDateString('en-CA');
        setType(btn.dataset.type || 'sold');
        setMode('existing');
        loadLeads(true);
        modal.show();
    });

    form.addEventListener('submit', e => {
        e.preventDefault();
        errorBox.classList.add('d-none');
        if (form.querySelector('input[name="buyer_mode"]:checked').value === 'existing' && !leadId.value) {
            errorBox.textContent = 'Pick a lead from the list, or switch to “New buyer”.';
            errorBox.classList.remove('d-none');
            search.focus();
            return;
        }
        const missing = [...form.querySelectorAll('input[type="file"]')].find(input => !input.files.length);
        if (missing) {
            errorBox.textContent = 'Upload both documents: ' + missing.closest('.col-md-6').querySelector('.form-label').textContent.trim() + ' is missing.';
            errorBox.classList.remove('d-none');
            return;
        }
        submit.disabled = true;
        fetch(`${base}${propertyId}/mark-sold`, { method: 'POST', headers, body: new FormData(form) })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Could not save. Please try again.'));
                return data;
            })
            .then(data => {
                modal.hide();
                const card = document.querySelector(`.portal-property-col[data-id="${propertyId}"]`);
                if (card) card.remove();
                window.location.href = data.redirect;
            })
            .catch(err => {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
            })
            .finally(() => { submit.disabled = false; });
    });
})();
</script>
@endpush
