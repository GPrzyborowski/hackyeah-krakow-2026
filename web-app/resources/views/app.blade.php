<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#eaf0ee">

        {{-- MomJobs uses a single light brand theme --}}
        <style>
            html {
                background-color: #eaf0ee;
                color-scheme: light;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        @php($appUrl = rtrim(config('app.url'), '/'))
        @php($appDescription = 'MomJobs – praca dla przyszłych i obecnych mam. Pracodawcy szukają Ciebie. Ty wybierasz, kiedy wracasz.')
        <meta name="description" content="{{ $appDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'MomJobs') }}">
        <meta property="og:locale" content="pl_PL">
        <meta property="og:title" content="{{ config('app.name', 'MomJobs') }} – Pracodawcy szukają Ciebie. Ty wybierasz, kiedy wracasz.">
        <meta property="og:description" content="{{ $appDescription }}">
        <meta property="og:url" content="{{ $appUrl.request()->getPathInfo() }}">
        <meta property="og:image" content="{{ $appUrl }}/og-image.png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="MomJobs – Pracodawcy szukają Ciebie. Ty wybierasz, kiedy wracasz.">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ config('app.name', 'MomJobs') }} – Pracodawcy szukają Ciebie. Ty wybierasz, kiedy wracasz.">
        <meta name="twitter:description" content="{{ $appDescription }}">
        <meta name="twitter:image" content="{{ $appUrl }}/og-image.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
