<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · '.config('app.name', 'PintarBelajar AI') : config('app.name', 'PintarBelajar AI') }}</title>

    {{-- Font Inter (privacy-friendly via Bunny Fonts). --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">

    <div x-data="{ sidebarOpen: false }" class="min-h-screen">

        {{-- Backdrop untuk drawer sidebar di mobile. --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity.duration.150ms
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
            style="display: none;"
            aria-hidden="true"
        ></div>

        {{-- Sidebar (Volt component dengan logout). --}}
        <livewire:layout.navigation />

        {{-- Area konten utama. --}}
        <div class="flex min-h-screen flex-col lg:pl-64">

            {{-- Topbar: hamburger di mobile + identitas user. --}}
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6 lg:px-8">
                <button
                    @click="sidebarOpen = true"
                    type="button"
                    class="-ml-1 inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden"
                    aria-label="Buka menu navigasi"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                @if (isset($header))
                    <div class="flex flex-1 items-center text-sm font-medium text-slate-600">
                        {{ $header }}
                    </div>
                @else
                    <div class="flex-1"></div>
                @endif

                <div class="flex items-center gap-3">
                    <span class="hidden text-sm font-medium text-slate-700 sm:inline-block">
                        {{ auth()->user()->name }}
                    </span>
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                        'bg-brand-50 text-brand-700 ring-brand-600/20' => auth()->user()->isAdmin(),
                        'bg-slate-100 text-slate-700 ring-slate-600/20' => auth()->user()->isUser(),
                    ])>
                        {{ auth()->user()->roleLabel() }}
                    </span>
                </div>
            </header>

            {{-- Konten halaman. --}}
            <main class="flex-1">
                {{ $slot }}
            </main>
        </div>
    </div>

</body>
</html>
