@extends('emails.layout')

@section('subject', $count . ' Facebook lead' . ($count === 1 ? '' : 's') . ' assigned to you — MW Realty')
@section('preheader', 'From the ' . $page->page_name . ' Facebook Page — one summary instead of an email per lead.')
@section('eyebrow', 'New leads for you')
@section('icon', '✓')
@section('tone', 'green')
@section('heading', $count . ' Facebook lead' . ($count === 1 ? '' : 's') . ' assigned to you')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $agent->displayName() }},</p>

<p style="margin:0;">
    The {{ mb_strtolower($contextLabel) }} of the Facebook Page <strong>{{ $page->page_name }}</strong> brought in leads,
    and <strong>{{ $count }}</strong> {{ $count === 1 ? 'was' : 'were' }} assigned to you. They're in your Leads now — please follow up.
</p>

<x-email.details :title="count($leads) < $count ? 'Your first ' . count($leads) . ' leads' : 'Your leads'">
    @foreach($leads as $lead)
    <x-email.row :label="$lead['name']" :strong="true">
        @if($lead['phone']){{ $lead['phone'] }}@endif
        @if($lead['phone'] && $lead['email']) · @endif
        @if($lead['email']){{ $lead['email'] }}@endif
        <br><span style="color:#838aa3;">{{ $lead['source'] }}@if($lead['campaign'] && $lead['campaign'] !== $lead['source']) · {{ $lead['campaign'] }}@endif{{ $lead['updated'] ? ' · existing lead, new enquiry' : '' }}</span>
        <br><a href="{{ route('portal.crm.leads.show', $lead['id']) }}" style="color:#04789a;">Open lead</a>
    </x-email.row>
    @endforeach
</x-email.details>

@if(count($leads) < $count)
<x-email.muted>…and {{ $count - count($leads) }} more. See them all in Leads.</x-email.muted>
@endif
@endsection

@section('cta_url', $leadsUrl)
@section('cta_label', 'View My Leads')
