@php
    [$heading, $tone, $icon, $eyebrow] = match ($event) {
        'submitted' => ['Listing submitted for approval', 'teal', '📄', 'Listing Approval'],
        'approved' => ['Your listing was approved', 'green', '✅', 'Listing Approved'],
        'changes_requested' => ['Your listing was taken down', 'amber', '📋', 'Listing Taken Down'],
        'expiring' => ['DLD permit expiring soon', 'amber', '⏳', 'Permit Reminder'],
        default => ['DLD permit expired', 'red', '⛔', 'Permit Expired'],
    };
@endphp
@extends('emails.layout')

@section('subject', (\App\Mail\ListingComplianceMail::SUBJECTS[$event] ?? 'Listing update') . ' — ' . $title)
@section('preheader', $heading . ': ' . $title)
@section('eyebrow', $eyebrow)
@section('icon', $icon)
@section('tone', $tone)
@section('heading', $heading)

@section('content')
<p style="margin:0 0 16px;">Hi {{ $recipient->displayName() }},</p>

@switch($event)
    @case('submitted')
        <p style="margin:0;"><strong>{{ $title }}</strong> was sent to MW Realty for approval. It will go live on the website as soon as it is approved — we'll email you either way.</p>
        @break
    @case('approved')
        <p style="margin:0;"><strong>{{ $title }}</strong> — MW Realty approved it, so it is now live on the website.</p>
        @break
    @case('changes_requested')
        <p style="margin:0;"><strong>{{ $title }}</strong> was taken off the website by MW Realty. Fix the points below, validate the permit in Core details and save the listing — it goes live again once the permit is verified.</p>
        @break
    @case('expiring')
        <p style="margin:0;">The DLD permit for <strong>{{ $title }}</strong> expires on <strong>{{ $property->permit_expires_at?->format('d M Y') }}</strong>. Renew it with DLD and add the new permit to the listing, or it will be taken off the website on that date.</p>
        @break
    @default
        <p style="margin:0;">The DLD permit for <strong>{{ $title }}</strong> expired, so the listing was taken off the website. Add the renewed permit in the listing's DLD Permit tab to send it for approval again.</p>
@endswitch

@if($note)
<x-email.callout :tone="$event === 'changes_requested' ? 'warning' : 'info'" title="{{ $event === 'changes_requested' ? 'What needs fixing' : 'Note from MW Realty' }}"><div style="white-space:pre-line;">{{ $note }}</div></x-email.callout>
@endif

<x-email.details title="Listing">
    <x-email.row label="Property" strong>{{ $title }}</x-email.row>
    <x-email.row label="Reference">{{ $property->reference_no }}</x-email.row>
    <x-email.row label="DLD permit">{{ $property->permit_number ?: '-' }}</x-email.row>
    <x-email.row label="Permit expires">{{ $property->permit_expires_at?->format('d M Y') ?? '-' }}</x-email.row>
</x-email.details>
@endsection

@section('cta_url', $listingUrl)
@section('cta_label', $event === 'approved' || $event === 'submitted' ? 'View Listing' : 'Update Listing')
