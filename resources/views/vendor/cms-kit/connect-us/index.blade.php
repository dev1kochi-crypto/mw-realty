@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Connect Us</li>
@endsection

@section('content')
@php
    $showLanguageUi = config('cms-kit.common.modules.languages', true);
    $extra = $section->extra_fields ?? [];
@endphp
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-primary">"Connect Us" Section Settings</h6>
    </div>
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('cms.connect-us.update') }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if($showLanguageUi)
            <ul class="nav nav-pills mb-4 bg-light p-2 rounded-4 language-switcher-tabs" id="langTabs" role="tablist">
                @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} px-4 py-2 fw-medium" id="tab-{{ $lang->code }}" data-bs-toggle="tab" data-bs-target="#panel-{{ $lang->code }}" type="button" role="tab">
                        <i class="fas fa-language me-2 opacity-75"></i>{{ $lang->name }}
                    </button>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="tab-content mb-4 language-switcher-content">
                @foreach($languages as $lang)
                @php $t = $section->translations[$lang->code] ?? []; @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="panel-{{ $lang->code }}" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="translations[{{ $lang->code }}][title]" class="form-control @error("translations.{$lang->code}.title") is-invalid @enderror" value="{{ old("translations.{$lang->code}.title", $t['title'] ?? '') }}" required>
                            @error("translations.{$lang->code}.title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Title 2</label>
                            <input type="text" name="translations[{{ $lang->code }}][title_2]" class="form-control" value="{{ old("translations.{$lang->code}.title_2", $t['title_2'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Button Text</label>
                            <input type="text" name="translations[{{ $lang->code }}][button_text]" class="form-control" value="{{ old("translations.{$lang->code}.button_text", $t['button_text'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Button URL</label>
                            <input type="url" name="translations[{{ $lang->code }}][button_url]" class="form-control" value="{{ old("translations.{$lang->code}.button_url", $t['button_url'] ?? '') }}" placeholder="https://...">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <hr>
            <h6 class="fw-bold mb-3">Video</h6>
            <div class="row g-3 mb-2">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="video_source" id="videoSourceUrl" value="url" {{ old('video_source', $extra['video_source'] ?? 'url') === 'url' ? 'checked' : '' }}>
                        <label class="form-check-label" for="videoSourceUrl">Video URL (YouTube/Vimeo)</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="video_source" id="videoSourceFile" value="file" {{ old('video_source', $extra['video_source'] ?? '') === 'file' ? 'checked' : '' }}>
                        <label class="form-check-label" for="videoSourceFile">Upload Video File</label>
                    </div>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Video URL</label>
                    <input type="url" name="video_url" class="form-control" value="{{ old('video_url', $extra['video_url'] ?? '') }}" placeholder="YouTube/Vimeo or self-hosted URL">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Video File</label>
                    <input type="file" name="video_file" class="form-control" accept="video/*">
                    <small class="text-muted d-block mt-1">Max: {{ $videoMaxSize }}KB</small>
                    @if(!empty($extra['video_file']))
                        <small class="text-muted d-block mt-1">Current: {{ basename($extra['video_file']) }}</small>
                    @endif
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="status" id="sectionStatus" {{ old('status', $section->status ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="sectionStatus">Status</label>
                    </div>
                </div>
            </div>

            <div class="col-12 border-top pt-4 mt-4">
                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                    <i class="fas fa-save me-2"></i>Update Section Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
