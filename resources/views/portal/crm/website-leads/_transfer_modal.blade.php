{{--
    Transfer dialog — shared by the Website Leads listing (ticked leads / every lead matching the
    filters) and a website lead's profile (that one lead). The opener fills the hidden inputs:
    window.openWebsiteLeadTransfer({ ids: [...], all: bool, exclude: [...], count: n }).
    The agency / agent picker searches on the server, 20 at a time, loading more on scroll.
--}}
<div class="modal fade" id="wlTransferModal" tabindex="-1" aria-labelledby="wlTransferTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('portal.crm.website-leads.transfer') }}" class="modal-content border-0 shadow-lg" id="wlTransferForm">
            @csrf
            <input type="hidden" name="from" value="{{ $transferFrom ?? 'listing' }}">
            <input type="hidden" name="portal_user_id" id="wlTarget">
            @foreach(['status', 'source', 'q'] as $filterKey)
            <input type="hidden" name="{{ $filterKey }}" value="{{ request($filterKey) }}">
            @endforeach
            <div id="wlTransferIds"></div>

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="wlTransferTitle"><i class="fas fa-share me-2"></i>Transfer <span id="wlTransferCount">lead</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Each lead becomes a CRM lead of the chosen agency or agent — they're notified, and their chat history and website insights go with it.</p>
                <label class="form-label small fw-semibold" for="wlTargetSearch">Agency or agent</label>
                <div class="wl-picker mb-3">
                    <input type="search" class="form-control" id="wlTargetSearch" placeholder="Search agencies and agents…" autocomplete="off">
                    <div class="wl-picker-list d-none" id="wlTargetList" data-url="{{ route('portal.crm.website-leads.targets') }}"></div>
                </div>
                <label class="form-label small fw-semibold" for="wlNote">Note <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="note" id="wlNote" class="form-control" rows="3" maxlength="1000" placeholder="Anything the agency should know"></textarea>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-portal-primary px-4" id="wlTransferBtn" disabled><i class="fas fa-share me-1"></i>Transfer</button>
            </div>
        </form>
    </div>
</div>

@once
@push('styles')
<style>
    .wl-picker { position: relative; }
    .wl-picker-list { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 30; max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid #e1e4ee; border-radius: 12px; box-shadow: 0 12px 28px rgba(20, 24, 50, .12); }
    .wl-picker-item { display: block; width: 100%; text-align: left; border: 0; background: none; padding: 8px 12px; font-size: 13px; }
    .wl-picker-item:hover, .wl-picker-item:focus { background: #f5f6fb; }
    .wl-picker-item small { display: block; color: #8a8fae; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('wlTransferModal');
    const search = document.getElementById('wlTargetSearch');
    const list = document.getElementById('wlTargetList');
    const hidden = document.getElementById('wlTarget');
    const button = document.getElementById('wlTransferBtn');
    const idsBox = document.getElementById('wlTransferIds');
    let page = 1, more = false, loading = false, term = '', timer = null, request = 0, count = 0;

    function addHidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        idsBox.appendChild(input);
    }

    window.openWebsiteLeadTransfer = function (selection) {
        idsBox.innerHTML = '';
        if (selection.all) {
            addHidden('all', '1');
            (selection.exclude || []).forEach((id) => addHidden('exclude[]', id));
        } else {
            (selection.ids || []).forEach((id) => addHidden('ids[]', id));
        }
        count = selection.count || (selection.ids || []).length;
        document.getElementById('wlTransferCount').textContent = count === 1 ? 'lead' : count + ' leads';
        hidden.value = '';
        search.value = '';
        button.disabled = true;
        list.innerHTML = '';
        list.classList.add('d-none');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    function load(reset) {
        if (loading && !reset) return;
        if (reset) { page = 1; list.innerHTML = ''; }
        loading = true;
        const id = ++request;
        fetch(list.dataset.url + '?' + new URLSearchParams({ q: term, page }), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                if (id !== request) return;
                data.results.forEach((item) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'wl-picker-item';
                    option.innerHTML = '<span class="fw-semibold"></span><small></small>';
                    option.children[0].textContent = item.text;
                    option.children[1].textContent = item.meta;
                    option.addEventListener('click', () => {
                        hidden.value = item.id;
                        search.value = item.text + ' (' + item.meta + ')';
                        list.classList.add('d-none');
                        button.disabled = false;
                    });
                    list.appendChild(option);
                });
                if (!list.children.length) list.innerHTML = '<div class="wl-picker-item text-muted">No matches.</div>';
                more = data.pagination.more;
                page += 1;
                list.classList.remove('d-none');
            })
            .finally(() => { if (id === request) loading = false; });
    }

    search.addEventListener('focus', () => { if (!list.children.length) load(true); else list.classList.remove('d-none'); });
    search.addEventListener('input', () => {
        hidden.value = '';
        button.disabled = true;
        clearTimeout(timer);
        timer = setTimeout(() => { term = search.value.trim(); load(true); }, 250);
    });
    list.addEventListener('scroll', () => {
        if (more && list.scrollTop + list.clientHeight >= list.scrollHeight - 30) load(false);
    });
    modalEl.addEventListener('click', (e) => { if (!e.target.closest('#wlTargetList') && e.target !== search) list.classList.add('d-none'); });
    document.getElementById('wlTransferForm').addEventListener('submit', (e) => {
        if (!hidden.value) { e.preventDefault(); return; }
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Transferring…';
    });
})();
</script>
@endpush
@endonce
