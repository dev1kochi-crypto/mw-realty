@extends('emails.layout')

@section('subject', 'New Enquiry — ' . $pageTitle)
@section('preheader', 'New enquiry on the ' . $pageTitle . ' landing page')
@section('eyebrow', 'Landing Page')
@section('icon', '✉')
@section('tone', 'teal')
@section('heading', 'New landing page enquiry')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0;">Someone just submitted a form on the <strong>{{ $pageTitle }}</strong> landing page.</p>

<x-email.details title="Enquiry details">
    @if($enquiry->name)<x-email.row label="Name" strong>{{ $enquiry->name }}</x-email.row>@endif
    @if($enquiry->email)<x-email.row label="Email">{{ $enquiry->email }}</x-email.row>@endif
    @if($enquiry->phone)<x-email.row label="Phone">{{ $enquiry->phone }}</x-email.row>@endif
    @if($enquiry->company)<x-email.row label="Company">{{ $enquiry->company }}</x-email.row>@endif
    @if($enquiry->country)<x-email.row label="Country">{{ $enquiry->country }}</x-email.row>@endif
    @if($enquiry->message)<x-email.row label="Message">{{ $enquiry->message }}</x-email.row>@endif
    @foreach($enquiry->extra_fields ?? [] as $key => $value)
    <x-email.row :label="\Illuminate\Support\Str::headline($key)">{{ is_array($value) ? implode(', ', $value) : $value }}</x-email.row>
    @endforeach
    <x-email.row label="Received">{{ $enquiry->created_at->format('d M Y, h:i A') }}</x-email.row>
</x-email.details>
@endsection

@section('cta_url', $reviewUrl)
@section('cta_label', 'View in Enquiries')
