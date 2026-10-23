Halo {{ $recipientName }},

{{ $headline }}

{{ $summary }}

@foreach ($rows as $label => $value)
{{ $label }}: {{ $value }}
@endforeach

Buka di portal: {{ $actionUrl }}

--
Email ini dikirim otomatis oleh {{ $appName }}. Mohon tidak membalas email ini.
