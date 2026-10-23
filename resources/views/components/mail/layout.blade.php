@props(['logo', 'appName', 'portalUrl', 'preheader' => ''])
<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5fb;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f1f5fb;font-size:1px;line-height:1px;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5fb;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e4e6ec;border-radius:8px;">
                    {{-- Header --}}
                    <tr>
                        <td style="padding:24px 32px;border-bottom:3px solid #0b5cf0;border-radius:8px 8px 0 0;">
                            <a href="{{ $portalUrl }}" style="text-decoration:none;">
                                <img src="{{ $logo }}" alt="{{ $appName }}" height="36" style="display:block;height:36px;width:auto;border:0;">
                            </a>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:#0b1530;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px;background-color:#f8fafd;border-top:1px solid #e4e6ec;border-radius:0 0 8px 8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:19px;color:#6d727e;">
                            Email ini dikirim otomatis oleh <a href="{{ $portalUrl }}" style="color:#0b5cf0;text-decoration:none;">{{ $appName }}</a>. Mohon tidak membalas email ini.
                            <br>
                            Butuh bantuan? Hubungi Owner QCC atau tim pengelola portal.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
