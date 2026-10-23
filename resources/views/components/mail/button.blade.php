@props(['url'])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td bgcolor="#0b5cf0" style="border-radius:8px;">
            <a href="{{ $url }}" style="display:inline-block;padding:13px 26px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;line-height:20px;color:#ffffff;text-decoration:none;border-radius:8px;">{{ $slot }}</a>
        </td>
    </tr>
</table>
