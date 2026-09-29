@props(['title' => null])
{{-- Grey key/value panel; fill it with <x-email.row> lines. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fc; border:1px solid #eceff5; border-radius:10px; margin:20px 0;">
    <tr><td style="padding:16px 20px;">
        @if($title)
        <div style="margin:0 0 8px; font-size:11px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; color:#838aa3;">{{ $title }}</div>
        @endif
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
            {{ $slot }}
        </table>
    </td></tr>
</table>
