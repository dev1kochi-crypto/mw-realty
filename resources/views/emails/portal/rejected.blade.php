@extends('emails.layout')

@section('subject', 'Update on Your Account Application')
@section('preheader', 'An update on your ' . $portalUser->type . ' account application.')
@section('eyebrow', 'Account')
@section('icon', '✉')
@section('tone', 'red')
@section('heading', 'Update on your application')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->name }},</p>

<p style="margin:0;">
    Thank you for registering as a <strong>{{ $portalUser->type }}</strong> on {{ config('cms-kit.common.name', 'MW Realty') }}'s partner portal.
    After review, we're unable to approve your account at this time.
</p>

@if($reason)
<x-email.callout tone="danger" title="Reason">{{ $reason }}</x-email.callout>
@endif

<p style="margin:16px 0 0;">
    If you believe this was a mistake or would like to provide more information, please contact our support team and we'll be happy to help.
</p>
@endsection
