{{-- How many of the viewer's leads use this item; clickable (opens the leads popup) when the viewer may change it. --}}
@if($editable && $item->leads_count > 0)
<button type="button" class="btn btn-link btn-sm p-0 linked-leads-btn" data-type="{{ $type }}" data-label="{{ $label }}" data-id="{{ $item->id }}" data-name="{{ $item->name }}" title="Show these leads">
    {{ number_format($item->leads_count) }} <i class="fas fa-arrow-up-right-from-square ms-1" style="font-size: .7rem;"></i>
</button>
@else
<span class="{{ $item->leads_count ? '' : 'portal-muted' }}">{{ number_format($item->leads_count) }}</span>
@endif
