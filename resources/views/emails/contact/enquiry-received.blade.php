@extends('emails.layout')

@section('subject', 'New Contact Us Enquiry')
@section('preheader', 'New enquiry' . ($enquiry->name ? ' from ' . $enquiry->name : '') . ' via the Contact Us form')
@section('eyebrow', 'Enquiry')
@section('icon', '✉')
@section('tone', 'teal')
@section('heading', 'New Contact Us enquiry')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0;">Someone just submitted the Contact Us form on the website.</p>

<x-email.details title="Enquiry details">
    @if($enquiry->name)<x-email.row label="Name" strong>{{ $enquiry->name }}</x-email.row>@endif
    @if($enquiry->email)<x-email.row label="Email">{{ $enquiry->email }}</x-email.row>@endif
    @if($enquiry->phone)<x-email.row label="Phone">{{ $enquiry->phone }}</x-email.row>@endif
    @if($enquiry->message)<x-email.row label="Message">{{ $enquiry->message }}</x-email.row>@endif
    <x-email.row label="Received">{{ $enquiry->created_at->format('d M Y, h:i A') }}</x-email.row>
</x-email.details>
@endsection

@section('cta_url', $reviewUrl)
@section('cta_label', 'View in Enquiries')
