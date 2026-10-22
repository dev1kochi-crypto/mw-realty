@props(['url', 'label' => 'View Details', 'tone' => 'teal'])
@php
    $bg = ['teal' => '#04a1cc', 'green' => '#0f9d58', 'red' => '#bd2545', 'navy' => '#264373'][$tone] ?? '#04a1cc';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" {{ $attributes->merge(['style' => 'margin:8px 0 0;']) }}>
    <tr><td style="border-radius:8px; background-color:{{ $bg }};">
        <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:12px 26px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:8px;">{{ $label }}</a>
    </td></tr>
</table>
