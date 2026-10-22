@extends('emails.layout')

@section('subject', 'Your ' . $documentLabel . ' has expired — MW Realty')
@section('preheader', 'Please update your ' . $documentLabel . ' to avoid any disruption to your account.')
@section('eyebrow', 'Action Needed')
@section('icon', '⚠')
@section('tone', 'amber')
@section('heading', 'Your ' . $documentLabel . ' has expired')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->displayName() }},</p>

<p style="margin:0;">
    Your <strong>{{ $documentLabel }}</strong> on file with MW Realty
    {{ $expiryDate ? 'expired on ' . $expiryDate->format('d M Y') : 'has expired' }}.
</p>

<x-email.callout tone="warning" title="What to do">
    Please upload the renewed document from your CRM profile as soon as possible to avoid any disruption to your account.
</x-email.callout>
@endsection

@section('cta_url', $profileUrl)
@section('cta_label', 'Update Profile')
