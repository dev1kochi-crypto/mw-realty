@extends('emails.layout')

@section('subject', 'KYC Document Updated — ' . $displayName)
@section('preheader', $displayName . ' updated their ' . $documentLabel . '.')
@section('eyebrow', 'KYC')
@section('icon', '📄')
@section('tone', 'teal')
@section('heading', 'KYC document updated')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0;">
    <strong>{{ $displayName }}</strong> (still pending approval) just uploaded or replaced their
    <strong>{{ $documentLabel }}</strong>. It's ready for another look.
</p>
@endsection

@section('cta_url', $reviewUrl)
@section('cta_label', 'Review Account')
