@props(['title' => null, 'tone' => 'info'])
@php
    [$bg, $bar, $titleColor] = [
        'info' => ['#eef8fc', '#04a1cc', '#04789a'],
        'success' => ['#edf8f2', '#0f9d58', '#0b7a44'],
        'warning' => ['#fef7ea', '#e08e0b', '#a8680a'],
        'danger' => ['#fdf2f2', '#dc3545', '#b8283a'],
        'neutral' => ['#f7f8fc', '#c7cedb', '#5b6478'],
    ][$tone] ?? ['#eef8fc', '#04a1cc', '#04789a'];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{{ $bg }}; border-left:4px solid {{ $bar }}; border-radius:6px; margin:18px 0;">
    <tr><td style="padding:14px 18px; font-size:13px; color:#1c2340; line-height:1.6;">
        @if($title)
        <div style="font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:0.05em; color:{{ $titleColor }}; margin-bottom:4px;">{{ $title }}</div>
        @endif
        {{ $slot }}
    </td></tr>
</table>
