@extends('portal.layouts.app')

@section('title', 'Contact Us')

@section('content')
<div class="mb-4">
    <div class="portal-section-title mb-0">Contact Us</div>
    <p class="text-muted mb-0" style="font-size: 0.88rem;">Need help with your account, a listing, or billing? Reach the MW Realty team directly.</p>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Get in touch</div>
            <ul class="list-unstyled mb-0">
                @if($siteInfo?->phone_1)
                <li class="d-flex align-items-center gap-3 mb-3">
                    <span class="portal-contact-icon"><i class="fas fa-phone"></i></span>
                    <div>
                        <div class="fw-semibold">{{ $siteInfo->phone_1 }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">Phone</div>
                    </div>
                </li>
                @endif
                @if($siteInfo?->whatsapp_number)
                <li class="d-flex align-items-center gap-3 mb-3">
                    <span class="portal-contact-icon"><i class="fab fa-whatsapp"></i></span>
                    <div>
                        <div class="fw-semibold">{{ $siteInfo->whatsapp_number }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">WhatsApp</div>
                    </div>
                </li>
                @endif
                @if($siteInfo?->email_1)
                <li class="d-flex align-items-center gap-3 mb-3">
                    <span class="portal-contact-icon"><i class="fas fa-envelope"></i></span>
                    <div>
                        <div class="fw-semibold">{{ $siteInfo->email_1 }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">Email</div>
                    </div>
                </li>
                @endif
                @if($siteInfo?->address)
                <li class="d-flex align-items-center gap-3 mb-3">
                    <span class="portal-contact-icon"><i class="fas fa-map-marker-alt"></i></span>
                    <div>
                        <div class="fw-semibold">{{ $siteInfo->address }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">Office</div>
                    </div>
                </li>
                @endif
                @if($siteInfo?->working_hours)
                <li class="d-flex align-items-center gap-3">
                    <span class="portal-contact-icon"><i class="fas fa-clock"></i></span>
                    <div>
                        <div class="fw-semibold">{{ $siteInfo->working_hours }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">Working Hours</div>
                    </div>
                </li>
                @endif
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="portal-card p-4">
            <div class="portal-section-title">Send a message</div>
            <form action="{{ route('portal.contact.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject</label>
                    <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" maxlength="150" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Message</label>
                    <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="6" required maxlength="2000">{{ old('message') }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-portal-primary">
                    <i class="fas fa-paper-plane me-1"></i> Send Message
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
