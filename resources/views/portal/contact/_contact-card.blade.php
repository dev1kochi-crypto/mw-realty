<div class="portal-card p-4">
    <div class="portal-section-title">Get in touch</div>
    <ul class="list-unstyled mb-0 st-contact">
        @foreach([
            ['phone_1', 'fas fa-phone', 'Phone'],
            ['whatsapp_number', 'fab fa-whatsapp', 'WhatsApp'],
            ['email_1', 'fas fa-envelope', 'Email'],
            ['address', 'fas fa-map-marker-alt', 'Office'],
            ['working_hours', 'fas fa-clock', 'Working Hours'],
        ] as [$field, $icon, $label])
        @if($siteInfo?->{$field})
        <li>
            <span class="portal-contact-icon"><i class="{{ $icon }}"></i></span>
            <div>
                <div class="fw-semibold">{{ $siteInfo->{$field} }}</div>
                <div class="text-muted" style="font-size: 0.78rem;">{{ $label }}</div>
            </div>
        </li>
        @endif
        @endforeach
    </ul>
    <p class="text-muted small mb-0 mt-3"><i class="fas fa-info-circle me-1"></i>For anything account-specific, a ticket is fastest — it keeps the whole conversation and its status in one place.</p>
</div>
