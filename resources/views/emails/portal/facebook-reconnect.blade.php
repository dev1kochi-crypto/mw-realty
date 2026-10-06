@extends('emails.layout')

@section('subject', 'Reconnect your Facebook Page "' . $connection->page_name . '" — MW Realty')
@section('preheader', 'New Facebook leads from ' . $connection->page_name . ' are paused until the Page is reconnected.')
@section('eyebrow', 'Action Needed')
@section('icon', '⚠')
@section('tone', 'amber')
@section('heading', 'Your Facebook Page needs reconnecting')

@section('content')
<p style="margin:0 0 16px;">Hi {{ $owner?->displayName() ?? 'there' }},</p>

<p style="margin:0;">
    Facebook stopped accepting MW Realty's access to your Page <strong>{{ $connection->page_name }}</strong>,
    so <strong>new Facebook Lead Ads leads from this Page are not reaching your CRM</strong> right now.
</p>

<x-email.details title="What happened">
    <x-email.row label="Page" :strong="true">{{ $connection->page_name }}</x-email.row>
    <x-email.row label="Failed on">{{ ($connection->needs_reconnect_at ?? now())->format('d M Y, H:i') }}</x-email.row>
    <x-email.row label="Reason">{{ $reason }}</x-email.row>
    <x-email.row label="Facebook said">{{ \Illuminate\Support\Str::limit($facebookMessage, 300) }}</x-email.row>
</x-email.details>

<x-email.callout tone="warning" title="How to reconnect">
    1. Sign in to MW Realty and open <strong>CRM › Integrations</strong>.<br>
    2. Click <strong>Reconnect</strong> next to {{ $connection->page_name }} (or <strong>Connect Facebook Page</strong>).<br>
    3. Log in with a Facebook account that is an <strong>admin of the Page</strong>, and allow all the permissions asked.<br>
    4. Tick <strong>{{ $connection->page_name }}</strong> and click <strong>Connect Pages</strong>.<br>
    5. Click <strong>Sync now</strong> on the Page to pull in any leads received while it was disconnected (up to the last 30 days).
</x-email.callout>

<x-email.muted>If MW Realty connected this Page for you, reply to this email and our team will reconnect it.</x-email.muted>
@endsection

@section('cta_url', $integrationsUrl)
@section('cta_label', 'Reconnect Page')
