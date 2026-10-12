{{-- One editable row of the add-on cost table ($i = row index, '__I__' in the add-row template). --}}
<tr>
    <td>
        <select name="rates[{{ $i }}][segment]" class="form-select form-select-sm" @disabled(!$canEdit)>
            <option value="">Any</option>
            <option value="residential" @selected($rate?->segment === 'residential')>Residential</option>
            <option value="commercial" @selected($rate?->segment === 'commercial')>Commercial</option>
        </select>
    </td>
    <td>
        <select name="rates[{{ $i }}][listing_type]" class="form-select form-select-sm" @disabled(!$canEdit)>
            <option value="">Any</option>
            @foreach($listingTypes as $value => $label)
                <option value="{{ $value }}" @selected($rate?->listing_type === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="rates[{{ $i }}][property_type]" class="form-select form-select-sm" @disabled(!$canEdit)>
            <option value="">Any</option>
            @foreach($propertyTypes as $value => $label)
                <option value="{{ $value }}" @selected($rate?->property_type === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="number" min="0" step="1" name="rates[{{ $i }}][min_price]" value="{{ $rate?->min_price !== null ? (float) $rate->min_price : '' }}" class="form-control form-control-sm" style="min-width: 110px;" placeholder="Any" @disabled(!$canEdit)></td>
    <td><input type="number" min="0" step="1" name="rates[{{ $i }}][max_price]" value="{{ $rate?->max_price !== null ? (float) $rate->max_price : '' }}" class="form-control form-control-sm" style="min-width: 110px;" placeholder="Any" @disabled(!$canEdit)></td>
    @foreach(['listing', 'premium_7', 'premium_15', 'premium_30'] as $column)
        <td><input type="number" min="0" step="1" name="rates[{{ $i }}][{{ $column }}]" value="{{ $rate?->{$column} ?? 0 }}" class="form-control form-control-sm" style="min-width: 80px;" required @disabled(!$canEdit)></td>
    @endforeach
    @if($canEdit)
        <td><button type="button" class="btn btn-sm btn-light text-danger" data-remove-rate title="Remove"><i class="fas fa-trash"></i></button></td>
    @endif
</tr>
