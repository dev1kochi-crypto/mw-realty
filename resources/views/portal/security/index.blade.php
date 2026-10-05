@extends('portal.layouts.app')

@section('title', 'Security')

@include('portal.two-factor._styles')

@php
    $tfaOn = $portalUser->hasTwoFactorEnabled();
    $tfaRequired = $portalUser->twoFactorRequired();
@endphp

@section('content')
<div class="sec-page">
    <div class="sec-head">
        <span class="sec-head__icon"><i class="fas fa-shield-halved"></i></span>
        <div>
            <h1 class="sec-head__title">Security</h1>
            <p class="sec-head__sub">Manage two-factor authentication and the devices that can sign in to your account.</p>
        </div>
    </div>

    <div class="sec-layout">
    <div class="sec-main">
    @if($portalUser->isAgency())
    <div class="sec-card">
        <div class="sec-card__title"><span>2-Factor Authentication (My Company)</span></div>
        <form method="POST" action="{{ route('portal.two-factor.enforce') }}" class="sec-row">
            @csrf
            <input type="hidden" name="enforce" value="{{ $portalUser->two_factor_enforced ? 0 : 1 }}">
            <div class="sec-method__desc">Enable mandatory 2FA for all users in my company. Agents without it will be asked to set it up before they can use the portal.</div>
            <div class="form-check form-switch m-0">
                <input class="form-check-input sec-switch" type="checkbox" role="switch" @checked($portalUser->two_factor_enforced) onchange="this.form.submit()" aria-label="Mandatory 2FA for my company">
            </div>
        </form>
    </div>
    @endif

    <div class="sec-card">
        <div class="sec-card__title">
            <span>2-Factor Authentication</span>
            <span class="sec-pill {{ $tfaOn ? 'is-on' : 'is-off' }}">{{ $tfaOn ? 'Enabled' : 'Disabled' }}</span>
        </div>
        <p class="sec-card__text">2-factor authentication adds an extra layer of security to your account. After your password, you'll enter a code from your phone.</p>
        @if($tfaRequired && !$portalUser->isAgency())
        <div class="alert alert-info py-2 small"><i class="fas fa-building me-1"></i>Your agency has made 2FA mandatory, so it can't be turned off.</div>
        @endif

        <div class="sec-methods">
            <div class="sec-methods__label">2-factor authentication methods</div>
            <div class="sec-method">
                <span class="sec-method__icon"><i class="fas fa-mobile-screen-button"></i></span>
                <div class="sec-method__body">
                    <div class="sec-method__name">Authenticator App @if($tfaOn)<span class="sec-chip is-green">Configured</span>@endif</div>
                    <div class="sec-method__desc">Use Authy, Google Authenticator or Microsoft Authenticator to get the OTP code.</div>
                </div>
                @if($tfaOn)
                <button type="button" class="btn btn-portal-light btn-sm" data-bs-toggle="modal" data-bs-target="#tfaPasswordModal"
                    data-tfa-action="{{ route('portal.two-factor.reconfigure') }}" data-tfa-title="Move to a new phone"
                    data-tfa-text="You'll scan a new QR code. Your current app keeps working until the new one is verified." data-tfa-button="Continue">Reconfigure</button>
                @else
                <a href="{{ route('portal.two-factor.setup') }}" class="btn btn-portal-primary btn-sm">Set up</a>
                @endif
            </div>
            <div class="sec-method">
                <span class="sec-method__icon"><i class="fas fa-envelope"></i></span>
                <div class="sec-method__body">
                    <div class="sec-method__name">New device email check @if($tfaOn)<span class="sec-chip is-green">On</span>@else<span class="sec-chip is-grey">Off</span>@endif</div>
                    <div class="sec-method__desc">{{ $tfaOn
                        ? 'Signing in from a new device or browser also sends a code to ' . $portalUser->email . '.'
                        : 'Turns on together with the authenticator app: new devices must then also enter a code sent to ' . $portalUser->email . '.' }}</div>
                </div>
            </div>
        </div>

        @if($tfaOn)
        <div class="sec-row flex-wrap mt-3">
            <div class="sec-method__desc"><i class="fas fa-key me-1"></i>{{ count($portalUser->two_factor_recovery_codes ?? []) }} of 8 recovery codes left
                · <a href="#" data-bs-toggle="modal" data-bs-target="#tfaPasswordModal" data-tfa-action="{{ route('portal.two-factor.recovery-codes') }}"
                    data-tfa-title="Generate new recovery codes" data-tfa-text="Your old recovery codes will stop working." data-tfa-button="Generate">Generate new codes</a></div>
            @unless($tfaRequired)
            <button type="button" class="btn btn-portal-danger btn-sm" data-bs-toggle="modal" data-bs-target="#tfaPasswordModal"
                data-tfa-action="{{ route('portal.two-factor.disable') }}" data-tfa-title="Disable 2FA"
                data-tfa-text="Signing in will only need your password (plus the email code on new devices)." data-tfa-button="Disable 2FA">Disable 2FA</button>
            @endunless
        </div>
        @endif
    </div>

    <div class="sec-card">
        <div class="sec-card__title">
            <span>Recognised devices</span>
            @if($trustedDevices->count() > 1)
            <form method="POST" action="{{ route('portal.two-factor.forget-devices') }}" class="m-0">@csrf
                <button class="btn btn-portal-light btn-sm">Forget other devices</button>
            </form>
            @endif
        </div>
        <p class="sec-card__text mb-1">Browsers that passed email verification. Any other device must enter an emailed code to sign in.</p>
        @forelse($trustedDevices as $device)
        <div class="sec-device">
            <span class="sec-device__icon"><i class="fas {{ preg_match('/Mobile|Android|iPhone/i', (string) $device->user_agent) ? 'fa-mobile-screen' : 'fa-laptop' }}"></i></span>
            <div class="flex-grow-1 text-truncate" title="{{ $device->user_agent }}">{{ \Illuminate\Support\Str::limit($device->user_agent ?: 'Unknown device', 80) }}</div>
            <div class="text-muted text-nowrap small">{{ $device->ip_address }} · {{ \Illuminate\Support\Carbon::parse($device->last_used_at)->diffForHumans() }}</div>
        </div>
        @empty
        <div class="sec-method__desc">No devices yet.</div>
        @endforelse
    </div>
    </div>

    {{-- Side panel: how the sign-in protection works, step by step. --}}
    <aside class="sec-aside">
        <div class="sec-card">
            <div class="sec-card__title"><span><i class="fas fa-route me-2 text-muted"></i>How it works</span></div>
            <ol class="sec-flow">
                <li class="{{ $tfaOn ? 'is-done' : 'is-current' }}">
                    <strong>Set up the authenticator app</strong>
                    <span>Click <em>Set up</em>, scan the QR code with Authy (or Google / Microsoft Authenticator) and enter the 6-digit code. Save your recovery codes.</span>
                </li>
                @if($portalUser->isAgency())
                <li class="{{ $portalUser->two_factor_enforced ? 'is-done' : ($tfaOn ? 'is-current' : '') }}">
                    <strong>Make it mandatory (optional)</strong>
                    <span>Turn on <em>2-Factor Authentication (My Company)</em>. Every agent in your company must then set up 2FA before using the portal.</span>
                </li>
                @endif
                <li class="{{ $tfaOn ? 'is-done' : '' }}">
                    <strong>Sign in with password + code</strong>
                    <span>Each login asks for your password, then the current code from the app.</span>
                </li>
                <li class="{{ $tfaOn ? 'is-done' : '' }}">
                    <strong>New device? Email check</strong>
                    <span>With 2FA on, signing in from a new computer or browser also asks for a code sent to your email. After that, the device is remembered.</span>
                </li>
            </ol>
        </div>
        <div class="sec-card sec-tip">
            <div class="fw-bold mb-1"><i class="fas fa-mobile-screen-button me-2"></i>Changing your phone?</div>
            <div class="sec-method__desc">Use <em>Reconfigure</em> and scan the new QR code. The old phone keeps working until the new one is verified. Lost your phone? Sign in with a recovery code, or ask MW Realty support to reset 2FA.</div>
        </div>
    </aside>
    </div>
</div>

<div class="modal fade" id="tfaPasswordModal" tabindex="-1" aria-labelledby="tfaPasswordModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="tfaPasswordForm" style="border: 0; border-radius: 18px;">
            @csrf
            <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="tfaPasswordModalTitle">Confirm your password</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="sec-method__desc" id="tfaPasswordText"></p>
                <label class="form-label fw-semibold" for="tfaPassword">Your password</label>
                <input type="password" name="password" id="tfaPassword" class="form-control" autocomplete="current-password" required>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-portal-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-portal-primary" id="tfaPasswordSubmit">Confirm</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('tfaPasswordModal');
    const form = document.getElementById('tfaPasswordForm');
    modal.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        form.action = btn.dataset.tfaAction;
        document.getElementById('tfaPasswordModalTitle').textContent = btn.dataset.tfaTitle;
        document.getElementById('tfaPasswordText').textContent = btn.dataset.tfaText || '';
        const submit = document.getElementById('tfaPasswordSubmit');
        submit.textContent = btn.dataset.tfaButton;
        submit.className = 'btn ' + (btn.classList.contains('btn-portal-danger') ? 'btn-portal-danger' : 'btn-portal-primary');
        form.password.value = '';
    });
    modal.addEventListener('shown.bs.modal', () => form.password.focus());
})();
</script>
@endpush
