@php $total = $summary['added'] + $summary['updated'] + $summary['skipped']; @endphp
@extends('emails.layout')

@section('subject', 'Lead import finished — MW Realty')
@section('preheader', $summary['added'] . ' added, ' . $summary['updated'] . ' updated, ' . $summary['skipped'] . ' skipped from ' . $summary['file'] . '.')
@section('eyebrow', $summary['format'])
@section('icon', '✓')
@section('tone', $summary['skipped'] ? 'amber' : 'green')
@section('heading', 'Your lead import has finished')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $recipientName }},</p>

<p style="margin:0;">
    <strong>{{ $summary['file'] }}</strong> has been imported. The attached file is your upload with an
    <strong>Import Status</strong> and <strong>Import Remarks</strong> column for every row — Added, Updated, or Skipped with the reason.
</p>

<x-email.details title="Summary">
    <x-email.row label="Rows in file">{{ number_format($total) }}</x-email.row>
    <x-email.row label="Added" :strong="true">{{ number_format($summary['added']) }}</x-email.row>
    <x-email.row label="Updated">{{ number_format($summary['updated']) }} <span style="color:#838aa3;">(existing lead with the same email / phone — added to its history)</span></x-email.row>
    <x-email.row label="Skipped">{{ number_format($summary['skipped']) }} <span style="color:#838aa3;">(reason in Import Remarks, in the attachment)</span></x-email.row>
</x-email.details>

@if(!empty($summary['agents']))
<x-email.details title="Assigned to agents (round robin)">
    @foreach($summary['agents'] as $agentName => $n)
    <x-email.row :label="number_format($n) . ' lead' . ($n === 1 ? '' : 's')">{{ $agentName }}</x-email.row>
    @endforeach
</x-email.details>
<x-email.muted>Each agent got one summary email of their leads.</x-email.muted>
@endif
@endsection

@section('cta_url', $leadsUrl)
@section('cta_label', 'View Leads')
