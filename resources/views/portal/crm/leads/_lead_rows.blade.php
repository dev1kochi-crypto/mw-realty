{{-- One page of lead rows — the listing's first page (_listing) and every page / sort / Quick
     search the server-side DataTable asks for (LeadController::index with dt=1). Cells follow the
     viewer's saved field order ($leadTableColumns, LeadTablePreferenceService). --}}
@foreach($leads as $lead)
<tr class="portal-lead-row" data-lead-id="{{ $lead->id }}" tabindex="0" aria-label="View {{ $lead->name ?: 'lead' }} details">
    <td data-lead-selection><input type="checkbox" class="form-check-input lead-select-checkbox" value="{{ $lead->id }}"></td>
    <td class="portal-lead-sn">{{ $leads->firstItem() + $loop->index }}</td>
    @foreach($leadTableColumns as $column)
    @include('portal.crm.leads._lead_cell')
    @endforeach
</tr>
@endforeach
