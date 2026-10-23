<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (config('app.portal_favicon_url'))
            <link rel="icon" href="{{ config('app.portal_favicon_url') }}">
            <link rel="apple-touch-icon" href="{{ config('app.portal_favicon_url') }}">
        @endif
        <meta name="description" content="{{ config('app.portal_description') }}">
        <meta name="keywords" content="{{ config('app.portal_keywords') }}">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
