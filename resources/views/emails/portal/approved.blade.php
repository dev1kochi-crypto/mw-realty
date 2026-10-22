@extends('emails.layout')

@section('subject', 'Your Account Has Been Approved')
@section('preheader', 'Your ' . $portalUser->type . ' account is approved — you can log in now.')
@section('eyebrow', 'Account')
@section('icon', '✓')
@section('tone', 'green')
@section('heading', 'Your account is approved')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->name }},</p>

<p style="margin:0;">
    Great news — your <strong>{{ $portalUser->type }}</strong> account on {{ config('cms-kit.common.name', 'MW Realty') }}'s partner portal has been <strong style="color:#0f9d58;">approved</strong>.
    You can now log in, manage your properties, and track your CRM leads.
</p>

<x-email.button :url="$loginUrl" label="Log In to Your Portal" tone="green" style="margin-top:24px;" />

<x-email.muted>If you weren't expecting this email, you can safely ignore it.</x-email.muted>
@endsection
