@extends('emails.layout')

@section('subject', $title . ' | MW Realty')
@section('preheader', $summary ?: $title)
@section('eyebrow', $contentType)

@section('content')
@if($imageUrl)
<a href="{{ $articleUrl }}" target="_blank" style="display:block; margin:0 0 24px;">
    <img src="{{ $imageUrl }}" alt="{{ $title }}" width="528" style="display:block; width:100%; max-width:528px; height:auto; border:0; border-radius:10px;">
</a>
@endif

<p style="margin:0 0 8px; color:#bd2545; font-size:12px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase;">{{ $contentType }}</p>
<h1 class="em-heading" style="margin:0 0 14px; font-size:25px; line-height:1.3; color:#1c2340;">{{ $title }}</h1>
@if($summary)
<p style="margin:0; color:#5b6478; font-size:15px; line-height:1.7;">{{ $summary }}</p>
@endif

<x-email.button :url="$articleUrl" label="Read the story" tone="red" style="margin-top:24px;" />
@endsection

@section('footer_note')
You’re receiving this email because you subscribed to MW Realty updates.
<a href="{{ $unsubscribeUrl }}" style="color:#264373; font-weight:bold;">Unsubscribe</a>
@endsection
