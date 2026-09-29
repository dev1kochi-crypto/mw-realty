{{-- Explains own vs Super Admin (global) items at the top of Master › Stage / Tag / Source. --}}
<div class="alert alert-light border small d-flex align-items-start gap-2 mb-3" style="border-radius: 12px;">
    <i class="fas fa-circle-info mt-1" style="color: var(--portal-primary);"></i>
    <div>
        @if($isAdmin)
            The {{ $what }} you add here are shared with <strong>every agency and agent</strong> — they can use them but not edit or delete them.
            Agencies and agents can also add their own {{ $what }}, which only they see.
        @else
            <strong><i class="fas fa-globe me-1"></i>MW Realty</strong> {{ $what }} are set for every account — you can use them but not change them.
            {{ ucfirst($what) }} you add are yours only, and you can edit or delete them.
        @endif
        A {{ \Illuminate\Support\Str::singular($what) }} still used by leads can't be deleted — click its lead count to remove it from those leads first.
    </div>
</div>
