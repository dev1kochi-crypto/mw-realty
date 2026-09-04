@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.llms-txt.index') }}">LLMs.txt / SEO</a></li>
    <li class="breadcrumb-item active">Edit Manual</li>
@stop

@section('content')
<div class="card glass-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Edit LLMs.txt Manually</h2>
                <p class="text-muted small mb-0">Directly modify the Markdown-style content of your llms.txt file.</p>
            </div>
            <a href="{{ route('cms.llms-txt.index') }}" class="btn btn-light border">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>

        <div class="alert alert-info border-0 shadow-sm mb-4">
            <div class="d-flex">
                <i class="fas fa-info-circle mt-1 me-3"></i>
                <div>
                    <strong>Pro Tip:</strong> Keep manual notes outside the <strong>CMS-KIT:LLMS</strong> markers so automatic URL updates can continue safely.
                </div>
            </div>
        </div>

        <form action="{{ route('cms.llms-txt.update') }}" method="POST">
            @csrf
            <div class="mb-4">
                <textarea name="content" class="form-control xml-editor" spellcheck="false" required>{{ old('content', $content) }}</textarea>
                @error('content')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-premium px-5 py-2">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

@stop
