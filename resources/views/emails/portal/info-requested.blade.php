@extends('emails.layout')

@section('subject', 'MW Realty needs a bit more information from you')
@section('preheader', 'Our team needs a bit more information to finish reviewing your account.')
@section('eyebrow', 'Account Review')
@section('icon', '📋')
@section('tone', 'amber')
@section('heading', 'We need a bit more information')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->displayName() }},</p>

<p style="margin:0;">Our team is reviewing your account and needs a bit more information from you:</p>

<x-email.callout tone="warning" title="Requested by our team"><div style="white-space:pre-line;">{{ $requestMessage }}</div></x-email.callout>

<p style="margin:0;">Please update your profile at your earliest convenience.</p>
@endsection

@section('cta_url', $profileUrl)
@section('cta_label', 'Update My Profile')
