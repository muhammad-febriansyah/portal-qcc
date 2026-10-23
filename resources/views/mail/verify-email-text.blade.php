Halo {{ $recipientName }},

Terima kasih sudah mendaftar di {{ $appName }}. Buka tautan di bawah untuk memverifikasi alamat email ini.

@foreach ($rows as $label => $value)
{{ $label }}: {{ $value }}
@endforeach

Verifikasi email: {{ $actionUrl }}

Jika Anda tidak merasa mendaftar, abaikan email ini. Akun tidak akan aktif tanpa verifikasi.

--
Email ini dikirim otomatis oleh {{ $appName }}. Mohon tidak membalas email ini.
