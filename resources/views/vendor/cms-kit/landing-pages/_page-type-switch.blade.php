{{-- Page type: "template" = the site's blog-detail design (fill in content / cover / date below),
     "custom" = your own HTML. Toggled by togglePageType() in _form-scripts. --}}
@php $pageType = $pageType ?? 'template'; @endphp
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h6 class="fw-bold mb-0"><i class="fas fa-layer-group text-primary me-2"></i>Page Type</h6>
    </div>
    <div class="card-body p-4 pt-3">
        <div class="row g-3 lp-type-options">
            <div class="col-md-6">
                <input class="btn-check page-type-radio" type="radio" name="page_type" id="typeTemplate" value="template" @checked($pageType === 'template')>
                <label class="lp-type-option" for="typeTemplate">
                    <span class="lp-type-option__icon"><i class="fas fa-newspaper"></i></span>
                    <span>
                        <span class="lp-type-option__title">Blog design</span>
                        <span class="lp-type-option__text">Uses the site's blog detail page layout — just add a cover image, date and content.</span>
                    </span>
                </label>
            </div>
            <div class="col-md-6">
                <input class="btn-check page-type-radio" type="radio" name="page_type" id="typeCustom" value="custom" @checked($pageType === 'custom')>
                <label class="lp-type-option" for="typeCustom">
                    <span class="lp-type-option__icon"><i class="fas fa-code"></i></span>
                    <span>
                        <span class="lp-type-option__title">Custom HTML</span>
                        <span class="lp-type-option__text">Write your own HTML, CSS and JS — full control over the design.</span>
                    </span>
                </label>
            </div>
        </div>
    </div>
</div>
