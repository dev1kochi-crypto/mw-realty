@extends('emails.layout')

@section('subject', 'New sign-in to your MW Realty account')
@section('preheader', 'Your sign-in verification code is ' . $code . '. It expires in 10 minutes.')
@section('icon', '🔐')
@section('tone', 'navy')
@section('heading', 'New device sign-in')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $name }},</p>

<p style="margin:0 0 20px;">Someone just entered your password to sign in to the MW Realty Partner Portal from a device we don't recognise. Enter this code to finish signing in. It expires in <strong>10 minutes</strong>.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
    <tr><td align="center" style="padding:20px; background-color:#f7f8fc; border:1px dashed #c7cedb; border-radius:12px; font-size:34px; font-weight:bold; letter-spacing:12px; color:#264373; font-family:'Courier New', Courier, monospace;">
        {{ $code }}
    </td></tr>
</table>

<x-email.details title="Sign-in attempt">
    <x-email.row label="Device">{{ $device }}</x-email.row>
    @if($ip)<x-email.row label="IP address">{{ $ip }}</x-email.row>@endif
    <x-email.row label="Time">{{ now()->format('d M Y, H:i') }} ({{ config('app.timezone') }})</x-email.row>
</x-email.details>

<x-email.callout title="Wasn't you?" tone="danger">
    Don't share this code with anyone. Someone else knows your password, so change it right away from the login page (Forgot your password?).
</x-email.callout>
@endsection
