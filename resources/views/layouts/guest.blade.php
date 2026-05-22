<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · '.config('app.name', 'PintarBelajar AI') : config('app.name', 'PintarBelajar AI') }}</title>

    {{-- Font Inter dari Bunny Fonts (privacy-friendly, tanpa Google tracking). --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translate3d(0, 14px, 0); }
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes blob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(20px, -18px) scale(1.06); }
            66% { transform: translate(-16px, 14px) scale(0.96); }
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.5; transform: scale(1.4); }
        }
        .anim-up   { animation: fadeInUp 0.65s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .anim-fade { animation: fadeIn 0.9s ease-out both; }
        .blob      { animation: blob 16s ease-in-out infinite; }
        .pulse-dot { animation: pulseDot 2.4s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
            .anim-up, .anim-fade, .blob, .pulse-dot { animation: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-white font-sans text-slate-900 antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">

        {{-- ──────────── Panel brand (kiri) ──────────── --}}
        <aside class="relative isolate overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 px-8 py-10 text-white lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-14">

            {{-- Lapisan dekoratif: dot pattern (mask fade-out di bawah). --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 -z-10 opacity-40"
                style="background-image: radial-gradient(circle at 0.5px 0.5px, rgba(255,255,255,0.18) 0.5px, transparent 0); background-size: 14px 14px; mask-image: linear-gradient(to bottom, black 30%, transparent 95%); -webkit-mask-image: linear-gradient(to bottom, black 30%, transparent 95%);"
            ></div>

            {{-- Floating blobs (animasi lambat memberikan kesan hidup). --}}
            <div aria-hidden="true" class="blob pointer-events-none absolute -right-32 -top-20 -z-10 h-80 w-80 rounded-full bg-brand-400/40 blur-3xl"></div>
            <div aria-hidden="true" class="blob pointer-events-none absolute -left-24 bottom-0 -z-10 h-72 w-72 rounded-full bg-brand-300/30 blur-3xl" style="animation-delay: -8s;"></div>

            {{-- Garis horisontal halus dekat header dan footer. --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-24 -z-10 h-px bg-gradient-to-r from-transparent via-white/15 to-transparent"></div>

            {{-- Link kembali ke beranda. --}}
            <a
                href="{{ route('landing') }}"
                wire:navigate
                class="anim-fade group inline-flex w-fit items-center gap-1.5 text-xs font-medium text-brand-100 transition hover:text-white lg:absolute lg:right-12 lg:top-14"
                style="animation-delay: 0.05s;"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 transition group-hover:-translate-x-0.5">
                    <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
                </svg>
                Kembali ke beranda
            </a>

            <header class="anim-up flex items-center gap-3" style="animation-delay: 0.1s;">
                <x-application-logo class="h-10 w-10 text-brand-900" />
                <span class="text-lg font-semibold tracking-tight">PintarBelajar AI</span>
            </header>

            <div class="mt-10 hidden lg:block">
                <div class="anim-up inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-50 backdrop-blur" style="animation-delay: 0.16s;">
                    <span aria-hidden="true" class="pulse-dot inline-block h-1.5 w-1.5 rounded-full bg-brand-200"></span>
                    Asisten belajar berbasis AI
                </div>

                <h1 class="anim-up mt-5 max-w-md text-4xl font-semibold leading-[1.1] tracking-tight text-white" style="animation-delay: 0.24s;">
                    Belajar lebih cepat, ditemani AI yang memahami materimu.
                </h1>

                <p class="anim-up mt-4 max-w-md text-base leading-relaxed text-brand-100" style="animation-delay: 0.32s;">
                    Unggah materi PDF, ajukan pertanyaan, dan biarkan
                    PintarBelajar AI meringkas, membuat kuis, serta menyusun
                    flashcard untukmu.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-brand-50">
                    @foreach ([
                        'Chat dengan dokumen via retrieval terkonteks (RAG).',
                        'Ringkasan otomatis untuk poin kunci.',
                        'Kuis dan flashcard adaptif dari materimu.',
                    ] as $i => $item)
                        <li class="anim-up flex items-start gap-3" style="animation-delay: {{ 0.4 + $i * 0.08 }}s;">
                            <span class="mt-0.5 inline-flex h-5 w-5 flex-none items-center justify-center rounded-full bg-white/15 ring-1 ring-white/20">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-3 w-3 text-white">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </span>
                            <span class="leading-relaxed">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <footer class="anim-fade mt-10 hidden text-xs text-brand-200 lg:block" style="animation-delay: 0.7s;">
                © {{ now()->year }} PintarBelajar AI · Tugas Sertifikasi BNSP
            </footer>
        </aside>

        {{-- ──────────── Panel form (kanan) ──────────── --}}
        <main class="flex items-center justify-center px-6 py-10 sm:px-10 lg:px-16">
            <div class="anim-up w-full max-w-md" style="animation-delay: 0.15s;">
                {{ $slot }}
            </div>
        </main>

    </div>
</body>
</html>
