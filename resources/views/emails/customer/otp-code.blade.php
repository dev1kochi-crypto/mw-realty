@extends('emails.layout')

@section('subject', 'Your MW Realty verification code')
@section('preheader', 'Your verification code is ' . $code . '. It expires in 10 minutes.')
@section('icon', '🔒')
@section('tone', 'navy')
@section('heading', 'Verify your email')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $name }},</p>

<p style="margin:0 0 20px;">Use this code to verify your email and finish creating your MW Realty account. It expires in <strong>10 minutes</strong>.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
    <tr><td align="center" style="padding:20px; background-color:#f7f8fc; border:1px dashed #c7cedb; border-radius:12px; font-size:34px; font-weight:bold; letter-spacing:12px; color:#264373; font-family:'Courier New', Courier, monospace;">
        {{ $code }}
    </td></tr>
</table>

<x-email.muted>If you didn't request this, you can safely ignore this email — nobody can use the code without access to your inbox.</x-email.muted>
@endsection
