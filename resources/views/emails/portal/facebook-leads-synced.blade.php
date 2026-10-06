@php $count = $summary['new'] + $summary['merged']; @endphp
@extends('emails.layout')

@section('subject', $count . ' Facebook lead' . ($count === 1 ? '' : 's') . ' synced from ' . $page->page_name . ' — MW Realty')
@section('preheader', $summary['new'] . ' new, ' . $summary['merged'] . ' added to existing leads, from ' . $page->page_name . '.')
@section('eyebrow', 'Facebook Lead Ads')
@section('icon', '✓')
@section('tone', 'green')
@section('heading', $count . ' Facebook lead' . ($count === 1 ? '' : 's') . ' synced')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $owner?->displayName() ?? 'there' }},</p>

<p style="margin:0;">
    The {{ mb_strtolower($contextLabel) }} of your Facebook Page <strong>{{ $page->page_name }}</strong> has finished.
    Here's what reached your CRM:
</p>

<x-email.details title="Summary">
    <x-email.row label="New leads" :strong="true">{{ number_format($summary['new']) }}</x-email.row>
    <x-email.row label="Existing leads updated">{{ number_format($summary['merged']) }} <span style="color:#838aa3;">(same person from another ad set — added to their history)</span></x-email.row>
    <x-email.row label="Already in CRM">{{ number_format($summary['skipped']) }} <span style="color:#838aa3;">(skipped)</span></x-email.row>
    <x-email.row label="Period">{{ $summary['since'] ? 'Since ' . \Illuminate\Support\Carbon::parse($summary['since'])->format('d M Y') : 'All leads (no date limit)' }}</x-email.row>
</x-email.details>

@if(!empty($summary['campaigns']))
<x-email.details title="By campaign">
    @foreach(array_slice($summary['campaigns'], 0, 15, true) as $campaign => $leads)
    <x-email.row :label="number_format($leads) . ' lead' . ($leads === 1 ? '' : 's')">{{ $campaign }}</x-email.row>
    @endforeach
</x-email.details>
@endif

@if(!empty($summary['agents']))
<x-email.details title="Assigned to agents (round robin)">
    @foreach($summary['agents'] as $agentId => $assigned)
    <x-email.row :label="number_format($assigned['count']) . ' lead' . ($assigned['count'] === 1 ? '' : 's')">{{ $summary['agent_names'][$agentId] ?? 'Agent #' . $agentId }}</x-email.row>
    @endforeach
</x-email.details>
<x-email.muted>Each agent got one email listing their leads.</x-email.muted>
@endif

<x-email.muted>Each lead's Source is its ad set (or ad) name, and the lead shows its campaign, ad set, ad and form.</x-email.muted>
@endsection

@section('cta_url', $leadsUrl)
@section('cta_label', 'View Leads')
