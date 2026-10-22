@extends('emails.layout')

@section('subject', 'Your ' . $planName . ' plan renews tomorrow')
@section('preheader', 'Your ' . $planName . ' plan auto-renews on ' . $renewsAt->format('d M Y') . ' for AED ' . number_format($amount, 2) . '.')
@section('eyebrow', 'Subscription')
@section('icon', '↻')
@section('tone', 'amber')
@section('heading', 'Your plan renews tomorrow')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $displayName }},</p>

<p style="margin:0;">
    Just a heads-up — your subscription auto-renews <strong>tomorrow, {{ $renewsAt->format('d M Y') }}</strong>.
    The card saved on your account will be charged automatically, so there's nothing you need to do to keep your listings live.
</p>

<x-email.details title="Upcoming renewal">
    <x-email.row label="Plan" strong>{{ $planName }} &middot; {{ ucfirst($interval) }}</x-email.row>
    <x-email.row label="Renewal date">{{ $renewsAt->format('l, d M Y') }}</x-email.row>
    <x-email.row label="Amount" strong>AED {{ number_format($amount, 2) }}</x-email.row>
</x-email.details>

@if($isPlanChange)
<x-email.callout tone="info" title="Plan change">
    The plan change you scheduled takes effect with this renewal, so you'll be billed for <strong>{{ $planName }}</strong> from tomorrow.
</x-email.callout>
@endif

<x-email.muted>
    Want to change plans or turn off auto-renew? You can do it from Plans in your portal any time before the renewal date.
    The amount shown is your plan price — any recurring coupon discount is applied automatically, and your invoice is emailed once the payment goes through.
</x-email.muted>
@endsection

@section('cta_url', $plansUrl)
@section('cta_label', 'Manage Subscription')
