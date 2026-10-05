@extends('emails.layout')

@section('subject', ($isResubmission ? 'Listing resubmitted for approval — ' : 'Listing waiting for approval — ') . $title)
@section('preheader', $accountName . ' submitted a listing\'s DLD permit for review.')
@section('eyebrow', 'Listing Approval')
@section('icon', '📄')
@section('tone', $isResubmission ? 'amber' : 'teal')
@section('heading', $isResubmission ? 'Listing resubmitted for approval' : 'New listing waiting for approval')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0;"><strong>{{ $accountName }}</strong> {{ $isResubmission ? 'updated and resubmitted' : 'submitted' }} a listing for approval. Please check its DLD permit before it goes live.</p>

<x-email.details title="Listing">
    <x-email.row label="Property" strong>{{ $title }}</x-email.row>
    <x-email.row label="Reference">{{ $property->reference_no }}</x-email.row>
    <x-email.row label="Price">{{ $property->currency ?: 'AED' }} {{ number_format((float) $property->price) }}</x-email.row>
    <x-email.row label="Account">{{ $accountName }}</x-email.row>
    @if($agentName)<x-email.row label="Agent">{{ $agentName }}</x-email.row>@endif
</x-email.details>

<x-email.details title="{{ (\App\Support\PermitRules::issuer($property->permit_type) ?? 'Advertising') . ' permit' }}">
    @if(\App\Support\PermitRules::requiresPermit($property->permit_type))
    <x-email.row label="Permit number" strong>{{ $property->permit_number }}</x-email.row>
    <x-email.row label="Expires">{{ $property->permit_expires_at?->format('d M Y') ?? '-' }}</x-email.row>
    <x-email.row label="Verified online">{{ $property->permit_verified_at && in_array($property->permit_verified_via, ['dld', 'adrec'], true) ? 'Yes' : 'No — check before approving' }}</x-email.row>
    @else
    <x-email.row label="Permit">Not required ({{ \App\Support\PermitRules::TYPES[$property->permit_type]['label'] ?? '-' }})</x-email.row>
    @endif
</x-email.details>

<x-email.callout tone="info" title="Before approving">Check the permit number on the DLD website (or scan the QR code) and make sure its details match the ad.</x-email.callout>
@endsection

@section('cta_url', $reviewUrl)
@section('cta_label', 'Review Listing')
