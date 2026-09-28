@extends('portal.layouts.app')

@section('title', 'Raise a Ticket')

@include('portal.contact._styles')

@section('content')
<div class="st-head">
    <div>
        <h1 class="st-head__title">Raise a Ticket</h1>
        <p class="st-head__sub">Tell us what's going on — pick the issue type so it reaches the right person faster.</p>
    </div>
    <a href="{{ route('portal.contact.index') }}" class="btn btn-portal-light btn-sm"><i class="fas fa-arrow-left me-1"></i>My Tickets</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="portal-card p-4">
            <form action="{{ route('portal.contact.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold" for="st-category">Issue Type <span class="text-danger">*</span></label>
                        <select id="st-category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                            <option value="" disabled @selected(!old('category'))>Select the type of issue</option>
                            @foreach(\App\Models\SupportTicket::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold" for="st-priority">Priority</label>
                        <select id="st-priority" name="priority" class="form-select @error('priority') is-invalid @enderror">
                            @foreach(\App\Models\SupportTicket::PRIORITIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="st-subject">Subject <span class="text-danger">*</span></label>
                        <input id="st-subject" type="text" name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" maxlength="150" required placeholder="A short summary of the issue">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="st-message">Describe the issue <span class="text-danger">*</span></label>
                        <textarea id="st-message" name="message" class="form-control @error('message') is-invalid @enderror" rows="7" required maxlength="5000" placeholder="What happened, what you expected, and any listing / invoice reference that helps us find it.">{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="st-attachment">Attachment <span class="text-muted fw-normal">(optional)</span></label>
                        <input id="st-attachment" type="file" name="attachment_document" class="form-control @error('attachment_document') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf">
                        <div class="form-text">A screenshot or PDF — JPG, PNG or PDF, up to 4 MB.</div>
                        @error('attachment_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-portal-primary"><i class="fas fa-paper-plane me-1"></i> Submit Ticket</button>
                    <a href="{{ route('portal.contact.index') }}" class="btn btn-portal-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        @include('portal.contact._contact-card')
    </div>
</div>
@endsection
