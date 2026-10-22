@props(['label', 'strong' => false])
<tr>
    <td class="em-label" valign="top" style="padding:5px 12px 5px 0; color:#838aa3; width:130px;">{{ $label }}</td>
    <td valign="top" style="padding:5px 0; color:#1c2340;{{ $strong ? ' font-weight:bold;' : '' }}">{{ $slot }}</td>
</tr>
