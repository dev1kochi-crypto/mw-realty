@extends('emails.layout')

@section('subject', 'Follow-up due: ' . $typeLabel . ' ' . $leadName)
@section('preheader', $typeLabel . ' ' . $leadName . ' — due ' . $dueLabel . '.')
@section('eyebrow', 'Lead follow-up')
@section('icon', '⏰')
@section('tone', 'amber')
@section('heading', 'A follow-up is due')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $displayName }},</p>

<p style="margin:0;">
    This is your reminder to <strong>{{ strtolower($typeLabel) === 'email' ? 'email' : strtolower($typeLabel) }} {{ $leadName }}</strong>.
    Once it's done, open the lead and mark the follow-up complete with what happened.
</p>

<x-email.details title="Follow-up">
    <x-email.row label="Type" strong>{{ $typeLabel }}</x-email.row>
    <x-email.row label="Due">{{ $dueLabel }}</x-email.row>
    <x-email.row label="Lead">{{ $leadName }}</x-email.row>
    @if($leadPhone)<x-email.row label="Phone">{{ $leadPhone }}</x-email.row>@endif
    @if($leadEmail)<x-email.row label="Email">{{ $leadEmail }}</x-email.row>@endif
    @if($note)<x-email.row label="Note">{{ $note }}</x-email.row>@endif
</x-email.details>

<x-email.muted>
    You get this because the lead is assigned to you (or to your account when no agent is assigned).
    Nothing has been sent to the customer — reminders are for your team only.
</x-email.muted>
@endsection

@section('cta_url', $leadUrl)
@section('cta_label', 'Open Lead')
