<!DOCTYPE html>
<html lang="{{ $lang }}" @if($isRtl) dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! $metaTags !!}
    <title>{{ $page->metadata['meta_title'] ?? $title }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 860px; margin: 3rem auto; padding: 0 1.5rem; line-height: 1.7; color: #1a1a1a; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div>{!! $content !!}</div>
</body>
</html>
