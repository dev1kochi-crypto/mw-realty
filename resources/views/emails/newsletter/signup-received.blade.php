@extends('emails.layout')

@section('subject', 'New Newsletter Signup')
@section('preheader', $signup->email . ' just subscribed to the newsletter')
@section('eyebrow', 'Newsletter')
@section('icon', '📰')
@section('tone', 'navy')
@section('heading', 'New newsletter subscriber')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0;">A new visitor just subscribed to the newsletter.</p>

<x-email.details>
    <x-email.row label="Email" strong>{{ $signup->email }}</x-email.row>
    <x-email.row label="Received">{{ $signup->created_at->format('d M Y, h:i A') }}</x-email.row>
</x-email.details>
@endsection

@section('cta_url', $reviewUrl)
@section('cta_label', 'View Subscribers')
