<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Platform belajar berbasis AI: unggah materi PDF, ajukan pertanyaan, dapatkan ringkasan, kuis, dan flashcard otomatis.">

    <title>{{ config('app.name', 'PintarBelajar AI') }} · Belajar lebih cepat ditemani AI</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translate3d(0, 18px, 0); }
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes blob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(18px, -22px) scale(1.06); }
            66% { transform: translate(-14px, 12px) scale(0.96); }
        }
        .anim-up { animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .anim-fade { animation: fadeIn 1s ease-out both; }
        .blob { animation: blob 14s ease-in-out infinite; }
    </style>
</head>
<body class="bg-white font-sans text-slate-900 antialiased">

    {{-- ─────────── Navbar ─────────── --}}
    <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/85 backdrop-blur">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-2.5">
                <x-application-logo class="h-8 w-8 text-brand-600" />
                <span class="text-base font-semibold tracking-tight text-slate-900">PintarBelajar AI</span>
            </a>

            <div class="hidden items-center gap-7 text-sm font-medium text-slate-600 md:flex">
                <a href="#fitur" class="transition hover:text-slate-900">Fitur</a>
                <a href="#cara-kerja" class="transition hover:text-slate-900">Cara Kerja</a>
                <a href="#cta" class="transition hover:text-slate-900">Mulai</a>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('login') }}"
                    class="hidden rounded-lg px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 sm:inline-flex"
                >
                    Masuk
                </a>
                <a
                    href="{{ route('register') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:ring-offset-2"
                >
                    Daftar Gratis
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                    </svg>
                </a>
            </div>
        </nav>
    </header>

    {{-- ─────────── Hero ─────────── --}}
    <section class="relative isolate overflow-hidden">
        {{-- Background dekorasi: gradient halus + blob emerald + dot pattern. --}}
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-50/40 via-white to-white"></div>
        <div aria-hidden="true" class="blob absolute -right-32 -top-32 -z-10 h-[28rem] w-[28rem] rounded-full bg-brand-200/40 blur-3xl"></div>
        <div aria-hidden="true" class="blob absolute -left-24 top-40 -z-10 h-72 w-72 rounded-full bg-brand-300/30 blur-3xl" style="animation-delay: -7s;"></div>
        <div
            aria-hidden="true"
            class="absolute inset-0 -z-10 opacity-[0.35]"
            style="background-image: radial-gradient(circle at 1px 1px, rgb(15 23 42 / 0.08) 1px, transparent 0); background-size: 28px 28px; mask-image: linear-gradient(to bottom, black 30%, transparent 90%);"
        ></div>

        <div class="mx-auto max-w-7xl px-4 pb-24 pt-20 sm:px-6 sm:pt-24 lg:px-8 lg:pt-28">
            <div class="mx-auto max-w-3xl text-center">
                <span class="anim-up inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-700" style="animation-delay: 0.05s;">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                    Asisten belajar berbasis AI
                </span>

                <h1 class="anim-up mt-6 text-4xl font-bold leading-[1.05] tracking-tight text-slate-900 sm:text-5xl lg:text-6xl" style="animation-delay: 0.12s;">
                    Belajar lebih cepat, ditemani
                    <span class="relative whitespace-nowrap">
                        <span class="relative z-10 text-brand-700">AI</span>
                        <span aria-hidden="true" class="absolute inset-x-0 bottom-1 -z-10 h-3 rounded bg-brand-200/60 sm:h-4"></span>
                    </span>
                    yang memahami materimu.
                </h1>

                <p class="anim-up mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600" style="animation-delay: 0.2s;">
                    Unggah materi PDF, ajukan pertanyaan, dan biarkan PintarBelajar AI
                    membuat ringkasan, kuis, hingga flashcard secara otomatis.
                    Fokus pada pemahaman, biar AI yang merapikan.
                </p>

                <div class="anim-up mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row" style="animation-delay: 0.28s;">
                    <a
                        href="{{ route('register') }}"
                        class="group inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-600 focus:ring-offset-2 sm:w-auto"
                    >
                        Daftar Gratis
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 transition group-hover:translate-x-0.5">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                        </svg>
                    </a>
                    <a
                        href="#fitur"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
                    >
                        Lihat Fitur
                    </a>
                </div>

                <p class="anim-up mt-6 text-xs text-slate-500" style="animation-delay: 0.36s;">
                    Gratis untuk dicoba. Tidak perlu kartu kredit.
                </p>
            </div>
        </div>
    </section>

    {{-- ─────────── Fitur ─────────── --}}
    <section id="fitur" class="border-t border-slate-200 bg-slate-50/60 py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-brand-700">Fitur</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    Semua yang kamu butuhkan untuk memahami materi
                </h2>
                <p class="mt-4 text-base text-slate-600">
                    Dari unggah dokumen hingga repetisi terjadwal — alur belajar
                    yang terkoneksi, ringkas, dan adaptif.
                </p>
            </div>

            <div class="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $features = [
                        [
                            'title' => 'Unggah PDF',
                            'desc'  => 'Tarik dan lepas materi PDF. Sistem mengekstrak teks otomatis.',
                            'path'  => 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
                        ],
                        [
                            'title' => 'Chat berbasis RAG',
                            'desc'  => 'Tanya jawab kontekstual dari dokumenmu sendiri, jawaban beracuan ke sumber.',
                            'path'  => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
                        ],
                        [
                            'title' => 'Ringkasan Otomatis',
                            'desc'  => 'Tangkap poin utama tiap bab tanpa harus membaca puluhan halaman.',
                            'path'  => 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
                        ],
                        [
                            'title' => 'Kuis Adaptif',
                            'desc'  => 'Soal dibuat dari materimu sendiri, menyesuaikan area yang perlu diperkuat.',
                            'path'  => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                        ],
                        [
                            'title' => 'Flashcard',
                            'desc'  => 'Poin kunci dibungkus jadi kartu untuk repetisi terjadwal.',
                            'path'  => 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z',
                        ],
                        [
                            'title' => 'Dashboard Progress',
                            'desc'  => 'Pantau dokumen tersimpan, kuis dikerjakan, dan progres belajar.',
                            'path'  => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
                        ],
                    ];
                @endphp

                @foreach ($features as $i => $f)
                    <article
                        class="anim-up group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md"
                        style="animation-delay: {{ 0.1 + $i * 0.08 }}s;"
                    >
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 transition group-hover:bg-brand-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $f['path'] }}" />
                            </svg>
                        </span>
                        <h3 class="mt-5 text-base font-semibold text-slate-900">
                            {{ $f['title'] }}
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">
                            {{ $f['desc'] }}
                        </p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ─────────── Cara Kerja ─────────── --}}
    <section id="cara-kerja" class="border-t border-slate-200 bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-brand-700">Cara Kerja</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    Tiga langkah, dari materi ke pemahaman
                </h2>
            </div>

            <div class="mt-14 grid grid-cols-1 gap-8 sm:grid-cols-3">
                @php
                    $steps = [
                        ['no' => '01', 'title' => 'Unggah', 'desc' => 'Pilih file PDF materi. Sistem akan mengekstrak teks dan menyiapkan untuk diproses.'],
                        ['no' => '02', 'title' => 'Proses AI', 'desc' => 'Konten dipecah jadi chunk dan diindeks ke vector store agar AI bisa mencari dengan presisi.'],
                        ['no' => '03', 'title' => 'Belajar',   'desc' => 'Tanya jawab, baca ringkasan, kerjakan kuis, dan tinjau flashcard sesuai ritmemu.'],
                    ];
                @endphp

                @foreach ($steps as $i => $s)
                    <div
                        class="anim-up relative rounded-2xl border border-slate-200 bg-white p-6"
                        style="animation-delay: {{ 0.1 + $i * 0.1 }}s;"
                    >
                        <span class="absolute -top-3 left-6 inline-flex items-center justify-center rounded-full bg-brand-600 px-3 py-1 text-xs font-bold tracking-wider text-white shadow-sm">
                            Langkah {{ $s['no'] }}
                        </span>
                        <h3 class="mt-3 text-lg font-semibold text-slate-900">
                            {{ $s['title'] }}
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">
                            {{ $s['desc'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ─────────── CTA penutup ─────────── --}}
    <section id="cta" class="px-4 py-20 sm:px-6 lg:px-8">
        <div class="relative isolate mx-auto max-w-5xl overflow-hidden rounded-3xl bg-brand-700 px-6 py-14 text-center shadow-xl sm:px-12 sm:py-20">
            {{-- dekorasi dalam CTA --}}
            <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand-400/40 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -left-12 h-64 w-64 rounded-full bg-brand-500/30 blur-3xl"></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 opacity-30"
                style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.25) 1px, transparent 0); background-size: 18px 18px;"
            ></div>

            <h2 class="relative text-3xl font-bold tracking-tight text-white sm:text-4xl">
                Siap mulai belajar lebih cerdas?
            </h2>
            <p class="relative mx-auto mt-4 max-w-xl text-base text-brand-100">
                Buat akun gratis, unggah materi pertamamu, dan rasakan
                perbedaannya dalam beberapa menit.
            </p>
            <div class="relative mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a
                    href="{{ route('register') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-50"
                >
                    Daftar Gratis
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                    </svg>
                </a>
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-white/30 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                >
                    Saya sudah punya akun
                </a>
            </div>
        </div>
    </section>

    {{-- ─────────── Footer ─────────── --}}
    <footer class="border-t border-slate-200 bg-white py-10">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 text-sm text-slate-500 sm:flex-row sm:px-6 lg:px-8">
            <div class="flex items-center gap-2.5">
                <x-application-logo class="h-6 w-6 text-brand-600" />
                <span class="font-semibold text-slate-700">PintarBelajar AI</span>
            </div>
            <p class="text-center text-xs sm:text-right">
                © {{ now()->year }} PintarBelajar AI · Tugas Sertifikasi BNSP Web Developer
            </p>
        </div>
    </footer>

</body>
</html>
