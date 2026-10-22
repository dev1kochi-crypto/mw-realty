@extends('emails.layout')

@section('subject', $subjectLine)
@section('preheader', $body)
@section('eyebrow', 'Agency')
@section('icon', '🤝')
@section('tone', 'navy')
@section('heading', $subjectLine)

@section('content')
<p style="margin:0 0 16px;">Hi {{ $recipientName }},</p>

<p style="margin:0;">{{ $body }}</p>

@if($actionUrl)
<x-email.button :url="$actionUrl" :label="$actionLabel ?? 'Open Portal'" style="margin-top:24px;" />
@endif

<x-email.muted>If you weren't expecting this email, you can safely ignore it.</x-email.muted>
@endsection
