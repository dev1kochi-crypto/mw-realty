{{-- One FAQ: question + answer in every active language, side by side. --}}
<div class="border rounded-3 p-3 bg-light" data-row>
    <div class="row g-3 align-items-start">
        @foreach($languages as $lang)
            @php $dir = $lang->code === 'ar' ? 'rtl' : 'ltr'; @endphp
            <div class="col-md">
                <span class="badge bg-white text-primary border mb-2">{{ $lang->name }}</span>
                <input type="text" dir="{{ $dir }}" name="faqs[{{ $index }}][{{ $lang->code }}][question]" class="form-control form-control-sm mb-2" value="{{ $row[$lang->code]['question'] ?? '' }}" placeholder="Question">
                <textarea dir="{{ $dir }}" name="faqs[{{ $index }}][{{ $lang->code }}][answer]" class="form-control form-control-sm" rows="3" placeholder="Answer">{{ $row[$lang->code]['answer'] ?? '' }}</textarea>
            </div>
        @endforeach
        <div class="col-md-auto d-flex flex-md-column align-items-end gap-2">
            <div class="form-check form-switch m-0" title="Shown on the page">
                <input class="form-check-input" type="checkbox" name="faqs[{{ $index }}][status]" value="1" {{ !empty($row['status']) ? 'checked' : '' }}>
            </div>
            @include('cms-kit::help-desk._row-actions')
        </div>
    </div>
</div>
