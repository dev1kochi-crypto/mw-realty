@extends('emails.layout')

@section('subject', 'New Enquiry — ' . $propertyLabel)
@section('preheader', 'New enquiry about ' . $propertyLabel . ($lead->name ? ' from ' . $lead->name : ''))
@section('eyebrow', 'New Lead')
@section('icon', '🏠')
@section('tone', 'teal')
@section('heading', 'You have a new enquiry')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $lead->owner?->displayName() ?? 'there' }},</p>

<p style="margin:0;">You just received a new enquiry about <strong>{{ $propertyLabel }}</strong>. Reach out soon — quick replies convert best.</p>

<x-email.details title="Lead details">
    @if($lead->name)<x-email.row label="Name" strong>{{ $lead->name }}</x-email.row>@endif
    @if($lead->email)<x-email.row label="Email">{{ $lead->email }}</x-email.row>@endif
    @if($lead->formatted_phone)<x-email.row label="Phone">{{ $lead->formatted_phone }}</x-email.row>@endif
    @if($lead->message)<x-email.row label="Message">{{ $lead->message }}</x-email.row>@endif
    <x-email.row label="Received">{{ $lead->created_at->format('d M Y, h:i A') }}</x-email.row>
</x-email.details>
@endsection

@section('cta_url', $reviewUrl)
@section('cta_label', 'View in CRM')
