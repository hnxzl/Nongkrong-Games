<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <title>@yield('title', 'Tongkrongan Games')</title>
    
    <!-- Prevent zoom on input focus -->
    <meta name="format-detection" content="telephone=no">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @stack('head')
</head>
<body class="game-container" x-data="{ loading: false }">
    {{-- Loading Overlay --}}
    <div x-show="loading" x-cloak 
         class="fixed inset-0 bg-slate-900/80 z-50 flex items-center justify-center">
        <div class="spinner"></div>
    </div>

    {{-- Main Content --}}
    <main class="flex-1 flex flex-col">
        @yield('content')
    </main>

    {{-- Toast Container --}}
    <div x-data="toast()" 
         x-on:show-toast.window="showToast($event.detail.message, $event.detail.type)"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         x-cloak
         :class="toastClass"
         class="toast text-white font-medium text-center">
        <span x-text="message"></span>
    </div>

    @stack('scripts')
</body>
</html>
