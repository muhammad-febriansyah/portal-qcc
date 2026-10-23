@props(['rows'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;border:1px solid #e4e6ec;border-radius:6px;border-collapse:separate;border-spacing:0;">
    @foreach ($rows as $label => $value)
        <tr>
            <td width="34%" valign="top" style="padding:11px 16px;{{ $loop->last ? '' : 'border-bottom:1px solid #eef0f5;' }}background-color:#f8fafd;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#6d727e;">{{ $label }}</td>
            <td valign="top" style="padding:11px 16px;{{ $loop->last ? '' : 'border-bottom:1px solid #eef0f5;' }}font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;color:#0b1530;">{{ $value }}</td>
        </tr>
    @endforeach
</table>
