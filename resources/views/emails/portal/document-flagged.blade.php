@extends('emails.layout')

@section('subject', 'Action Needed: ' . $documentLabel)
@section('preheader', 'Your ' . $documentLabel . ' needs another look — please re-upload it.')
@section('eyebrow', 'Action Needed')
@section('icon', '⚑')
@section('tone', 'red')
@section('heading', $documentLabel . ' needs another look')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $portalUser->displayName() }},</p>

<p style="margin:0;">Your <strong>{{ $documentLabel }}</strong> needs another look. Please re-upload it from your profile.</p>

@if($note)
<x-email.callout tone="danger" title="Admin's note">{{ $note }}</x-email.callout>
@endif
@endsection

@section('cta_url', $profileUrl)
@section('cta_label', 'Update My Profile')
