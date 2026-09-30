{{-- One floor plan row. $i is the row index ("__INDEX__" inside the Add Row template), $fp its saved values. --}}
@php $image = !empty($fp['image']) ? media_url($fp['image']) : null; @endphp
<div class="floor-plan-card floor-plan-row">
    <label class="floor-plan-thumb {{ $image ? 'has-image' : '' }}" title="Choose floor plan image">
        <input type="hidden" name="floor_plans[{{ $i }}][existing_image]" value="{{ $fp['image'] ?? '' }}">
        <input type="file" name="floor_plans[{{ $i }}][image]" class="floor-plan-image-input" accept="image/*">
        <img src="{{ $image ?? '' }}" alt="" @if(!$image) hidden @endif>
        <span class="floor-plan-thumb-empty"><i class="fas fa-image"></i><span>Add image</span></span>
        <span class="floor-plan-thumb-edit" aria-hidden="true"><i class="fas fa-camera"></i></span>
    </label>

    <div class="floor-plan-fields">
        <div class="floor-plan-field floor-plan-field--title">
            <label class="form-label small mb-1">Title</label>
            <input type="text" name="floor_plans[{{ $i }}][label]" class="form-control form-control-sm" value="{{ $fp['label'] ?? '' }}" placeholder="1 Bedroom Apartments">
        </div>
        <div class="floor-plan-field">
            <label class="form-label small mb-1">Sqft From</label>
            <input type="number" min="0" name="floor_plans[{{ $i }}][size_from]" class="form-control form-control-sm" value="{{ $fp['size_from'] ?? '' }}" placeholder="0">
        </div>
        <div class="floor-plan-field">
            <label class="form-label small mb-1">Sqft To</label>
            <input type="number" min="0" name="floor_plans[{{ $i }}][size_to]" class="form-control form-control-sm" value="{{ $fp['size_to'] ?? '' }}" placeholder="0">
        </div>
    </div>

    <button type="button" class="floor-plan-remove floor-plan-remove-btn" title="Remove row" aria-label="Remove row"><i class="fas fa-trash-can"></i></button>
</div>
