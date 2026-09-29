@extends('emails.layout')

@section('subject', 'New Career Application')
@section('preheader', $candidate->name . ($candidate->apply_for ? ' applied for ' . $candidate->apply_for : ' submitted a job application'))
@section('eyebrow', 'Careers')
@section('icon', '💼')
@section('tone', 'navy')
@section('heading', 'New career application')

@section('content')
<p style="margin:0 0 16px;">Hi Admin,</p>

<p style="margin:0;">A new job application was just submitted on the Careers page.</p>

<x-email.details title="Applicant">
    @if($candidate->apply_for)<x-email.row label="Position" strong>{{ $candidate->apply_for }}</x-email.row>@endif
    <x-email.row label="Name" strong>{{ $candidate->name }}</x-email.row>
    <x-email.row label="Email">{{ $candidate->email }}</x-email.row>
    @if($candidate->phone)<x-email.row label="Phone">{{ $candidate->phone }}</x-email.row>@endif
    @if($candidate->experience)<x-email.row label="Experience">{{ $candidate->experience }}</x-email.row>@endif
    @if($candidate->designation)<x-email.row label="Current Role">{{ $candidate->designation }}</x-email.row>@endif
    @if($candidate->country)<x-email.row label="Country">{{ $candidate->country }}</x-email.row>@endif
    @if($candidate->additional_information)<x-email.row label="Message">{{ $candidate->additional_information }}</x-email.row>@endif
    <x-email.row label="Received">{{ $candidate->submitted_at?->format('d M Y, h:i A') }}</x-email.row>
</x-email.details>
@endsection

@section('cta_url', $reviewUrl)
@section('cta_label', 'View Application')
