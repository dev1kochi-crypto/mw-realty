@extends('emails.layout')

@section('subject', $isResubmission ? 'We received your updated KYC details' : 'We received your KYC details')
@section('preheader', 'Our team is reviewing your account — we will email you once it is approved.')
@section('eyebrow', 'Account Review')
@section('icon', '📨')
@section('tone', 'teal')
@section('heading', $isResubmission ? 'Updated details received' : 'KYC details received')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->displayName() }},</p>

<p style="margin:0 0 16px;">
    Thanks — we've received your {{ $isResubmission ? 'updated ' : '' }}KYC details and documents.
    Our team is reviewing them now.
</p>

<p style="margin:0;">We'll email you as soon as your account is approved, or if we need anything else from you.</p>
@endsection

@section('cta_url', $profileUrl)
@section('cta_label', 'View My Profile')
