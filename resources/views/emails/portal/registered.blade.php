@extends('emails.layout')

@section('subject', ($isResubmission ?? false) ? 'Resubmitted for Review — ' . $displayName : 'New ' . ucfirst($portalUser->type) . ' Registration — ' . $displayName)
@section('preheader', $displayName . (($isResubmission ?? false) ? ' resubmitted their KYC profile for review.' : ' submitted their KYC profile for approval.'))
@section('eyebrow', 'Registration')
@section('icon', '👤')
@section('tone', ($isResubmission ?? false) ? 'amber' : 'teal')
@section('heading', ($isResubmission ?? false) ? 'Profile resubmitted for review' : 'New ' . $portalUser->type . ' registration')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

@if($isResubmission ?? false)
<p style="margin:0;"><strong>{{ $portalUser->type }}</strong> {{ $displayName }} has updated their KYC profile and resubmitted it for review.</p>
@else
<p style="margin:0;"><strong>{{ $portalUser->type }}</strong> {{ $displayName }} has submitted their KYC profile for approval.</p>
@endif

<x-email.details title="Account">
    <x-email.row label="Name" strong>{{ $displayName }}</x-email.row>
    <x-email.row label="Type">{{ ucfirst($portalUser->type) }}</x-email.row>
    <x-email.row label="Email">{{ $portalUser->email }}</x-email.row>
    <x-email.row label="Phone">{{ $portalUser->phone ?: '-' }}</x-email.row>
    <x-email.row label="Registered">{{ $portalUser->created_at->format('d M Y, h:i A') }}</x-email.row>
</x-email.details>

<x-email.callout :tone="$missingDocuments->isNotEmpty() ? 'warning' : 'success'" title="KYC documents">
    <strong>{{ $documentsUploadedCount }} of {{ $documentsTotalCount }} uploaded.</strong>
    @if($missingDocuments->isNotEmpty())
        <br>Still missing: {{ $missingDocuments->implode(', ') }}.
    @endif
</x-email.callout>
@endsection

@section('cta_url', $reviewUrl)
@section('cta_label', 'Review Account')
