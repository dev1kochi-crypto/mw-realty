@extends('emails.layout')

@section('subject', 'KYC Document Updated — ' . $displayName)

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0 0 16px;">
    <strong>{{ $displayName }}</strong> (still pending approval) just uploaded or replaced their
    <strong>{{ $documentLabel }}</strong>. It's ready for another look.
</p>

@section('cta_url', $reviewUrl)
@section('cta_label', 'Review Account')
@endsection
