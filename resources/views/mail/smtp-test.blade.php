@php($logoCid = $message->embed($logoPath))
<x-mail.layout :logo="$logoCid" :app-name="$appName" :portal-url="$portalUrl" preheader="Konfigurasi email aplikasi dapat mengirim pesan.">
    <h1 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:21px;line-height:28px;font-weight:bold;color:#0b1530;">Uji SMTP berhasil</h1>

    <p style="margin:0;font-size:15px;line-height:24px;color:#2c3550;">Konfigurasi email aplikasi dapat mengirim pesan. Jika email ini sampai di inbox, notifikasi email siap dipakai.</p>

    <x-mail.details :rows="$rows" />
</x-mail.layout>
