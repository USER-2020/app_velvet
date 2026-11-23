<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#090a10">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="apple-touch-icon" sizes="192x192" href="/pwa-192x192.png">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script>
            const velvetPublicPath = window.location.pathname === '/' ||
                window.location.pathname === '/login' ||
                window.location.pathname.startsWith('/register') ||
                window.location.pathname === '/apply' ||
                window.location.pathname.startsWith('/apply/draft') ||
                window.location.pathname === '/politica-de-privacidad' ||
                window.location.pathname === '/terminos-y-condiciones' ||
                window.location.pathname.startsWith('/wishlist/share/') ||
                window.location.pathname.startsWith('/prequalification/') ||
                window.location.pathname.startsWith('/entrevista/') ||
                window.location.pathname.startsWith('/contratacion/') ||
                window.location.pathname.startsWith('/forgot-password') ||
                window.location.pathname.startsWith('/reset-password') ||
                window.location.pathname.startsWith('/verify-email') ||
                window.location.pathname.startsWith('/confirm-password');
            document.documentElement.dataset.theme = velvetPublicPath
                ? 'dark'
                : (localStorage.getItem('velvet-theme') || 'dark');
        </script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
