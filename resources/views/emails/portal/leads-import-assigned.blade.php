@extends('emails.layout')

@section('subject', $count . ' imported lead' . ($count === 1 ? '' : 's') . ' assigned to you — MW Realty')
@section('preheader', 'From a lead import by ' . $agencyName . ' — one summary instead of an email per lead.')
@section('eyebrow', 'New leads for you')
@section('icon', '✓')
@section('tone', 'green')
@section('heading', $count . ' lead' . ($count === 1 ? '' : 's') . ' assigned to you')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $agent->displayName() }},</p>

<p style="margin:0;">
    {{ $agencyName }} imported a lead file, and <strong>{{ $count }}</strong> {{ $count === 1 ? 'lead was' : 'leads were' }} assigned to you.
    They're in your Leads now — please follow up.
</p>

<x-email.details title="Summary">
    <x-email.row label="Assigned to you" :strong="true">{{ number_format($count) }}</x-email.row>
    <x-email.row label="New leads">{{ number_format($newCount) }}</x-email.row>
    @if($updatedCount)
    <x-email.row label="Existing leads, new enquiry">{{ number_format($updatedCount) }}</x-email.row>
    @endif
</x-email.details>

@if(count($sources) > 1)
<x-email.details title="By source">
    @foreach($sources as $source => $n)
    <x-email.row :label="$source">{{ number_format($n) }}</x-email.row>
    @endforeach
</x-email.details>
@endif
@endsection

@section('cta_url', $leadsUrl)
@section('cta_label', 'View My Leads')
