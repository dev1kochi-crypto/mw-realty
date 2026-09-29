{{-- Marks a Super Admin (global) Stage / Tag / Source in an agency's or agent's list. --}}
@if($item->isGlobal())
<span class="badge rounded-pill ms-1" style="background: rgba(36,67,115,.1); color: var(--portal-primary); font-size: .66rem; font-weight: 700;" title="Set by MW Realty for every account — you can use it, but not change it.">
    <i class="fas fa-globe me-1"></i>MW Realty
</span>
@endif
