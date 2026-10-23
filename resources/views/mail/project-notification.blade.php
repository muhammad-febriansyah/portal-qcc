@php($logoCid = $message->embed($logoPath))
<x-mail.layout :logo="$logoCid" :app-name="$appName" :portal-url="$portalUrl" :preheader="$summary">
    <p style="margin:0 0 20px;font-size:15px;line-height:24px;color:#0b1530;">Halo {{ $recipientName }},</p>

    <h1 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:21px;line-height:28px;font-weight:bold;color:#0b1530;">{{ $headline }}</h1>

    <p style="margin:0;font-size:15px;line-height:24px;color:#2c3550;">{{ $summary }}</p>

    <x-mail.details :rows="$rows" />

    <x-mail.button :url="$actionUrl">Buka di portal</x-mail.button>

    <p style="margin:24px 0 0;font-size:12px;line-height:19px;color:#6d727e;">
        Jika tombol tidak berfungsi, salin tautan ini ke browser:<br>
        <a href="{{ $actionUrl }}" style="color:#0b5cf0;word-break:break-all;">{{ $actionUrl }}</a>
    </p>
</x-mail.layout>
