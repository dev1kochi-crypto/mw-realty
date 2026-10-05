@extends('portal.layouts.app')

@section('title', 'Two-Factor Authentication')

@include('portal.two-factor._styles')

@php
    $doneUrl = $onboarding ? route('portal.dashboard') : route('portal.security');
@endphp

@section('content')
<div class="sec-page">
    <div class="sec-head">
        <span class="sec-head__icon"><i class="fas fa-shield-halved"></i></span>
        <div>
            @if($onboarding)<div class="sec-head__eyebrow">Last step · Secure your account</div>@endif
            <h1 class="sec-head__title">{{ $reconfiguring ? 'Move 2FA to a new phone' : 'Two-Factor Authentication' }}</h1>
            <p class="sec-head__sub">{{ $reconfiguring
                ? 'Scan the new QR code with your new phone. Your current app keeps working until you verify the new one.'
                : 'Protect your account with a 6-digit code from an authenticator app every time you sign in.' }}</p>
        </div>
    </div>

    @if($recoveryCodes)
        {{-- Just enabled / regenerated: the recovery codes are shown this one time only. --}}
        <div class="sec-card" style="max-width: 620px; padding: 2rem;">
            <span class="tfa-success-icon"><i class="fas fa-check"></i></span>
            <h2 class="h5 fw-bold mb-2">Save your recovery codes</h2>
            <p class="sec-card__text mb-0">If you lose your phone, sign in with one of these codes instead of the app code. Each code works <strong>once</strong>. Keep them somewhere safe; they won't be shown again.</p>
            <div class="tfa-codes">
                @foreach($recoveryCodes as $rc)<span>{{ $rc }}</span>@endforeach
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-portal-light btn-sm" id="tfaCopy"><i class="fas fa-copy me-1"></i>Copy</button>
                <button type="button" class="btn btn-portal-light btn-sm" id="tfaDownload"><i class="fas fa-download me-1"></i>Download</button>
                <a href="{{ $doneUrl }}" class="btn btn-portal-primary btn-sm ms-auto">I've saved them, continue <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    @else
        @if($required && !$reconfiguring)
        <div class="alert alert-warning d-flex gap-2 align-items-center"><i class="fas fa-building-shield"></i>Your company requires two-factor authentication. Set it up to continue using the portal.</div>
        @elseif($enforcing)
        <div class="alert alert-info d-flex gap-2 align-items-center"><i class="fas fa-building-shield"></i>Set up 2FA on your own account first. Mandatory 2FA for your company turns on automatically when you finish.</div>
        @endif

        <div class="sec-card p-0">
            <div class="tfa-grid">
                <div class="tfa-steps">
                    <div class="tfa-step">
                        <span class="tfa-step__num">1</span>
                        <div>
                            <div class="tfa-step__title">Install an authenticator app</div>
                            <div class="tfa-step__text">Any of these free apps works on iPhone and Android.</div>
                            <div class="tfa-apps"><span>Authy</span><span>Google Authenticator</span><span>Microsoft Authenticator</span></div>
                        </div>
                    </div>
                    <div class="tfa-step">
                        <span class="tfa-step__num">2</span>
                        <div>
                            <div class="tfa-step__title">Scan the QR code</div>
                            <div class="tfa-step__text">In the app, tap <strong>+</strong> / <strong>Add account</strong> and scan the code on this page. "MW Realty" will appear in your app.</div>
                        </div>
                    </div>
                    <div class="tfa-step">
                        <span class="tfa-step__num">3</span>
                        <div class="flex-grow-1">
                            <div class="tfa-step__title">Enter the 6-digit code</div>
                            <div class="tfa-step__text">Type the code the app shows for MW Realty.</div>
                            <form method="POST" action="{{ route('portal.two-factor.confirm') }}" id="tfaForm">
                                @csrf
                                @if($onboarding)<input type="hidden" name="onboarding" value="1">@endif
                                <input type="hidden" name="code" id="tfaCode">
                                <div class="tfa-otp @error('code') is-invalid @enderror" id="tfaOtp">
                                    @for($i = 0; $i < 6; $i++)
                                        @if($i === 3)<span class="tfa-otp__gap"></span>@endif
                                        <input type="text" inputmode="numeric" maxlength="1" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" aria-label="Digit {{ $i + 1 }}" @if($i === 0) autofocus @endif>
                                    @endfor
                                </div>
                                @error('code')<div class="tfa-error"><i class="fas fa-circle-exclamation me-1"></i>{{ $message }}</div>@enderror
                                <button type="submit" class="btn btn-portal-primary px-4"><i class="fas fa-lock me-2"></i>{{ $reconfiguring ? 'Verify new phone' : 'Verify & Enable' }}</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tfa-qr-panel">
                    <div class="tfa-qr">{!! $qrSvg !!}</div>
                    <div class="tfa-qr-label">Can't scan? Enter this key in the app instead:</div>
                    <div class="tfa-key">
                        <span id="tfaKey">{{ trim(chunk_split($secret, 4, ' ')) }}</span>
                        <button type="button" id="tfaKeyCopy" title="Copy key" aria-label="Copy key"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>
        </div>

        @if($reconfiguring || !$required)
        <div class="text-center">
            @if($onboarding && !$reconfiguring)
            {{-- New accounts stay on this step until they set it up or explicitly skip. --}}
            <form method="POST" action="{{ route('portal.two-factor.skip') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-link p-0 tfa-footer-link">Skip for now, I'll do this later from Security</button>
            </form>
            @else
            <a href="{{ route('portal.security') }}" class="tfa-footer-link">Cancel</a>
            @endif
        </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    // 6 single-digit boxes → hidden "code" field; auto-advance, backspace, paste, auto-submit.
    const otp = document.getElementById('tfaOtp');
    if (otp) {
        const boxes = [...otp.querySelectorAll('input')];
        const form = document.getElementById('tfaForm');
        const sync = () => {
            const code = boxes.map(b => b.value).join('');
            document.getElementById('tfaCode').value = code;
            return code;
        };
        const fill = (start, digits) => {
            digits.split('').forEach((d, i) => { if (boxes[start + i]) boxes[start + i].value = d; });
            boxes[Math.min(start + digits.length, boxes.length - 1)].focus();
            if (sync().length === 6) form.requestSubmit();
        };
        boxes.forEach((box, i) => {
            box.addEventListener('input', () => {
                const digits = box.value.replace(/\D/g, '');
                box.value = '';
                if (digits) fill(i, digits);
                else sync();
            });
            box.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && !box.value && i > 0) { boxes[i - 1].value = ''; boxes[i - 1].focus(); sync(); e.preventDefault(); }
                if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                if (e.key === 'ArrowRight' && i < 5) boxes[i + 1].focus();
            });
            box.addEventListener('paste', e => {
                e.preventDefault();
                const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                if (digits) fill(0, digits);
            });
            box.addEventListener('focus', () => box.select());
        });
        form.addEventListener('submit', e => {
            if (sync().length !== 6) { e.preventDefault(); boxes.find(b => !b.value)?.focus(); }
        });
    }

    const copy = (btn, text) => navigator.clipboard.writeText(text).then(() => {
        const html = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>' + (btn.id === 'tfaKeyCopy' ? '' : ' Copied');
        setTimeout(() => { btn.innerHTML = html; }, 1500);
    });
    const keyCopy = document.getElementById('tfaKeyCopy');
    if (keyCopy) keyCopy.addEventListener('click', () => copy(keyCopy, document.getElementById('tfaKey').textContent.replace(/\s/g, '')));

    @if($recoveryCodes)
    const codes = @json(implode("\n", $recoveryCodes));
    const copyBtn = document.getElementById('tfaCopy');
    copyBtn.addEventListener('click', () => copy(copyBtn, codes));
    document.getElementById('tfaDownload').addEventListener('click', function () {
        const blob = new Blob(['MW Realty recovery codes ({{ $portalUser->email }})\n\n' + codes + '\n'], { type: 'text/plain' });
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'mw-realty-recovery-codes.txt' });
        a.click();
        URL.revokeObjectURL(a.href);
    });
    @endif
})();
</script>
@endpush
