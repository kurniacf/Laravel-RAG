@php
    use App\Models\Document;
    use App\Models\Quiz;
    use App\Models\Summary;

    $statusBadge = [
        Document::STATUS_PENDING    => 'bg-slate-100 text-slate-700 ring-slate-600/20',
        Document::STATUS_PROCESSING => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        Document::STATUS_READY      => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        Document::STATUS_FAILED     => 'bg-red-50 text-red-700 ring-red-600/20',
    ];

    $diffBadge = [
        Quiz::DIFFICULTY_EASY   => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        Quiz::DIFFICULTY_MEDIUM => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        Quiz::DIFFICULTY_HARD   => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    ];

    $d = $this->document;
    $summaries = $this->summaries;
    $hasSummaries = $summaries->isNotEmpty();
    $hasChunks = ($d->total_chunks ?? 0) > 0;
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl space-y-6">

        {{-- Breadcrumb / kembali. --}}
        <a
            href="{{ route('documents.index') }}"
            wire:navigate
            class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Kembali ke Dokumen
        </a>

        {{-- Flash. --}}
        @if (session('status'))
            <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- ───────── Kartu header dokumen ───────── --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
                <span class="inline-flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-6 w-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ $d->title }}</h1>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $statusBadge[$d->status] ?? '' }}">
                            {{ $d->statusLabel() }}
                        </span>
                    </div>
                    <p class="mt-1 truncate text-sm text-slate-500">{{ $d->original_filename }}</p>

                    @if ($d->subject)
                        <div class="mt-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: {{ $d->subject->color_hex }}1a; color: {{ $d->subject->color_hex }};">
                                {{ $d->subject->name }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Meta grid. --}}
            <dl class="grid grid-cols-2 gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-4">
                <div class="bg-white px-6 py-3">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Halaman</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-slate-900 tabular-nums">{{ $d->page_count ?? '—' }}</dd>
                </div>
                <div class="bg-white px-6 py-3">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kata</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-slate-900 tabular-nums">{{ $d->word_count ? number_format($d->word_count) : '—' }}</dd>
                </div>
                <div class="bg-white px-6 py-3">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Ukuran</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-slate-900 tabular-nums">{{ number_format($d->file_size_bytes / 1024, 1) }} KB</dd>
                </div>
                <div class="bg-white px-6 py-3">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Vektor</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-slate-900 tabular-nums">
                        {{ $d->total_chunks > 0 ? $d->total_chunks.' chunk' : 'Belum' }}
                    </dd>
                </div>
            </dl>

            {{-- Action bar. --}}
            <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                @if ($d->isReady() && $d->total_chunks === 0)
                    <button
                        type="button"
                        wire:click="indexToVector"
                        wire:loading.attr="disabled"
                        wire:target="indexToVector"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-50 px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                        </svg>
                        <span wire:loading.remove wire:target="indexToVector">Proses ke Vector</span>
                        <span wire:loading wire:target="indexToVector">Memproses...</span>
                    </button>
                @elseif ($d->total_chunks > 0)
                    <a
                        href="{{ route('chat.index') }}"
                        wire:navigate
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-700"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                        </svg>
                        Chat dengan Dokumen
                    </a>
                @endif

                @if (! $d->isReady())
                    <p class="text-sm text-slate-500">
                        Aksi AI tersedia setelah dokumen berstatus "Siap".
                    </p>
                @endif
            </div>
        </div>

        {{-- ───────── Ringkasan otomatis ───────── --}}
        <div
            class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            x-data="{ tab: '{{ Summary::TYPE_EXECUTIVE }}' }"
        >
            {{-- Overlay loading saat generate. --}}
            <div
                wire:loading.flex
                wire:target="generateSummary"
                class="absolute inset-0 z-20 flex-col items-center justify-center gap-3 bg-white/90 backdrop-blur-sm"
            >
                <svg class="h-8 w-8 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                </svg>
                <p
                    class="text-sm font-medium text-slate-700"
                    x-data="{
                        msgs: [
                            'Menyiapkan teks materi...',
                            'Membuat ringkasan eksekutif...',
                            'Menyusun ringkasan per bagian...',
                            'Menarik poin-poin kunci...',
                            'Merapikan hasil...',
                        ],
                        idx: 0,
                    }"
                    x-init="setInterval(() => idx = (idx + 1) % msgs.length, 2400)"
                    x-text="msgs[idx]"
                ></p>
                <p class="text-xs text-slate-400">Memanggil Gemini — proses ini bisa memakan beberapa detik.</p>
            </div>

            {{-- Header section ringkasan. --}}
            <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5 text-brand-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                        </svg>
                        Ringkasan Otomatis
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">Tiga tingkat ringkasan dari isi dokumen, dibuat oleh AI.</p>
                </div>

                @if ($hasSummaries && $d->isReady())
                    <div class="flex flex-wrap items-center gap-2 self-start">
                        <a
                            href="{{ route('documents.summary.pdf', $d) }}"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100"
                            title="Unduh ringkasan sebagai PDF"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Unduh PDF
                        </a>
                        <button
                            type="button"
                            wire:click="generateSummary"
                            wire:loading.attr="disabled"
                            wire:target="generateSummary"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Buat ulang
                        </button>
                    </div>
                @endif
            </div>

            {{-- Konten. --}}
            @if (! $hasSummaries)
                {{-- Empty state. --}}
                <div class="flex flex-col items-center px-6 py-14 text-center">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-7 w-7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                        </svg>
                    </span>
                    <p class="mt-4 text-sm font-semibold text-slate-800">Dokumen ini belum punya ringkasan</p>
                    <p class="mt-1 max-w-md text-sm text-slate-500">
                        Buat ringkasan eksekutif, per bagian, dan poin kunci secara otomatis dari isi dokumen.
                    </p>

                    @if ($d->isReady())
                        <button
                            type="button"
                            wire:click="generateSummary"
                            wire:loading.attr="disabled"
                            wire:target="generateSummary"
                            class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                            </svg>
                            Buat Ringkasan
                        </button>
                    @else
                        <p class="mt-5 rounded-lg bg-slate-50 px-4 py-2 text-xs font-medium text-slate-500">
                            Dokumen harus berstatus "Siap" sebelum dapat diringkas.
                        </p>
                    @endif
                </div>
            @else
                {{-- Tab bar. --}}
                <div class="flex gap-1 border-b border-slate-200 px-4 pt-3">
                    @foreach (Summary::TYPES as $type)
                        <button
                            type="button"
                            x-on:click="tab = '{{ $type }}'"
                            :class="tab === '{{ $type }}'
                                ? 'border-brand-600 text-brand-700'
                                : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition"
                        >
                            {{ Summary::labelFor($type) }}
                        </button>
                    @endforeach
                </div>

                {{-- Panel per tipe. --}}
                <div class="px-6 py-5">
                    @foreach (Summary::TYPES as $type)
                        @php $summary = $summaries->get($type); @endphp
                        <div x-show="tab === '{{ $type }}'" x-cloak>
                            @if (! $summary)
                                <p class="text-sm text-slate-500">Ringkasan tipe ini belum tersedia.</p>
                            @elseif ($type === Summary::TYPE_EXECUTIVE)
                                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $summary->content }}</p>
                            @elseif ($type === Summary::TYPE_PER_CHAPTER)
                                @php $sections = $summary->sections(); @endphp
                                @if (count($sections) === 1 && $sections[0]['title'] === '')
                                    {{-- Struktur per bagian tidak terdeteksi — tampilkan apa adanya + keterangan. --}}
                                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                                        Struktur per bagian tidak terdeteksi pada hasil ini. Klik "Buat ulang" bila ingin mencoba lagi.
                                    </p>
                                    <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $sections[0]['body'] }}</p>
                                @else
                                    <div class="space-y-4">
                                        @foreach ($sections as $i => $section)
                                            <div class="border-l-2 border-brand-200 pl-4">
                                                @if ($section['title'] !== '')
                                                    <h3 class="text-sm font-semibold text-slate-900">
                                                        <span class="text-brand-600">{{ $i + 1 }}.</span>
                                                        {{ $section['title'] }}
                                                    </h3>
                                                @endif
                                                @if ($section['body'] !== '')
                                                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $section['body'] }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                {{-- Poin kunci. --}}
                                @php $points = $summary->points(); @endphp
                                @if (empty($points))
                                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                                        Poin kunci tidak terdeteksi pada hasil ini. Klik "Buat ulang" untuk mencoba lagi.
                                    </p>
                                @else
                                    <ul class="space-y-2.5">
                                        @foreach ($points as $point)
                                            <li class="flex gap-2.5">
                                                <span class="mt-0.5 inline-flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span class="text-sm leading-relaxed text-slate-700">{{ $point }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endif

                            @if ($summary)
                                <p class="mt-5 border-t border-slate-100 pt-3 text-xs text-slate-400">
                                    {{ number_format($summary->word_count ?? 0) }} kata
                                    <span class="mx-1 text-slate-300">·</span>
                                    {{ $summary->model_used ?? 'AI' }}
                                    <span class="mx-1 text-slate-300">·</span>
                                    diperbarui {{ $summary->updated_at?->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ───────── Kuis ───────── --}}
        <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            {{-- Overlay loading saat generate kuis. --}}
            <div
                wire:loading.flex
                wire:target="generateQuiz"
                class="absolute inset-0 z-20 flex-col items-center justify-center gap-3 bg-white/90 backdrop-blur-sm"
            >
                <svg class="h-8 w-8 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                </svg>
                <p
                    class="text-sm font-medium text-slate-700"
                    x-data="{
                        msgs: [
                            'Mengambil potongan materi...',
                            'Menyusun soal kuis...',
                            'Memvalidasi struktur soal...',
                            'Menyimpan kuis...',
                        ],
                        idx: 0,
                    }"
                    x-init="setInterval(() => idx = (idx + 1) % msgs.length, 2400)"
                    x-text="msgs[idx]"
                ></p>
                <p class="text-xs text-slate-400">Memanggil Gemini — mohon tunggu beberapa detik.</p>
            </div>

            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5 text-brand-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    Kuis
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">Uji pemahaman dengan soal yang dibuat otomatis dari isi dokumen.</p>
            </div>

            @unless ($hasChunks)
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">Kuis belum bisa dibuat</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Soal disusun dari potongan (chunk) dokumen. Klik
                        <span class="font-medium text-slate-700">"Proses ke Vector"</span> di atas terlebih dahulu.
                    </p>
                </div>
            @else
                {{-- Form pembuatan kuis. --}}
                <div class="border-b border-slate-200 bg-slate-50/70 px-6 py-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="quiz-count" class="text-xs font-medium text-slate-600">Jumlah soal</label>
                            <select
                                id="quiz-count"
                                wire:model="quizQuestionCount"
                                class="mt-1 block rounded-lg border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                            >
                                <option value="5">5 soal</option>
                                <option value="8">8 soal</option>
                                <option value="10">10 soal</option>
                            </select>
                        </div>
                        <div>
                            <label for="quiz-difficulty" class="text-xs font-medium text-slate-600">Tingkat kesulitan</label>
                            <select
                                id="quiz-difficulty"
                                wire:model="quizDifficulty"
                                class="mt-1 block rounded-lg border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                            >
                                @foreach (Quiz::DIFFICULTIES as $diff)
                                    <option value="{{ $diff }}">{{ Quiz::difficultyLabelFor($diff) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button
                            type="button"
                            wire:click="generateQuiz"
                            wire:loading.attr="disabled"
                            wire:target="generateQuiz"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Buat Kuis
                        </button>
                    </div>
                    @error('quizQuestionCount') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('quizDifficulty') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Daftar kuis. --}}
                @if ($this->quizzes->isEmpty())
                    <div class="px-6 py-10 text-center">
                        <p class="text-sm font-medium text-slate-700">Belum ada kuis</p>
                        <p class="mt-1 text-xs text-slate-500">Pilih jumlah soal dan tingkat kesulitan, lalu klik "Buat Kuis".</p>
                    </div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($this->quizzes as $quiz)
                            <li wire:key="quiz-{{ $quiz->id }}" class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $diffBadge[$quiz->difficulty] ?? '' }}">
                                            {{ $quiz->difficultyLabel() }}
                                        </span>
                                        <span class="text-xs text-slate-400">{{ $quiz->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-1 text-sm font-medium text-slate-800">
                                        {{ $quiz->question_count }} soal
                                        <span class="mx-1 text-slate-300">·</span>
                                        dikerjakan {{ $quiz->total_attempts }}×
                                        @if ($quiz->average_score !== null)
                                            <span class="mx-1 text-slate-300">·</span>
                                            rata-rata {{ $quiz->average_score }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex flex-none items-center gap-1">
                                    <a
                                        href="{{ route('quizzes.show', $quiz) }}"
                                        wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-brand-700"
                                    >
                                        Kerjakan
                                    </a>
                                    {{-- Dropdown unduh PDF: lengkap (key=1) vs lembar soal saja (key=0). --}}
                                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                        <button
                                            type="button"
                                            @click="open = ! open"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-brand-200 bg-brand-50 px-2.5 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100"
                                            title="Unduh kuis sebagai PDF"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                            PDF
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3" :class="open && 'rotate-180'">
                                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition.opacity.duration.100ms
                                            class="absolute right-0 z-10 mt-1 w-56 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                                        >
                                            <a
                                                href="{{ route('quizzes.export.pdf', ['quiz' => $quiz, 'key' => 1]) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50"
                                            >
                                                <span class="font-semibold">Lengkap</span>
                                                <span class="block text-[10px] text-slate-500">Soal + kunci jawaban + pembahasan</span>
                                            </a>
                                            <a
                                                href="{{ route('quizzes.export.pdf', ['quiz' => $quiz, 'key' => 0]) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="block border-t border-slate-100 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50"
                                            >
                                                <span class="font-semibold">Lembar Soal</span>
                                                <span class="block text-[10px] text-slate-500">Tanpa kunci, siap dicetak & dikerjakan</span>
                                            </a>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="deleteQuiz({{ $quiz->id }})"
                                        wire:confirm="Hapus kuis ini beserta seluruh soal dan riwayat pengerjaannya?"
                                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endunless
        </div>

        {{-- ───────── Flashcard ───────── --}}
        <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            {{-- Overlay loading saat generate flashcard. --}}
            <div
                wire:loading.flex
                wire:target="generateFlashcards"
                class="absolute inset-0 z-20 flex-col items-center justify-center gap-3 bg-white/90 backdrop-blur-sm"
            >
                <svg class="h-8 w-8 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                </svg>
                <p
                    class="text-sm font-medium text-slate-700"
                    x-data="{
                        msgs: [
                            'Mengambil potongan materi...',
                            'Menyusun kartu depan-belakang...',
                            'Memvalidasi kartu...',
                            'Menyimpan flashcard...',
                        ],
                        idx: 0,
                    }"
                    x-init="setInterval(() => idx = (idx + 1) % msgs.length, 2400)"
                    x-text="msgs[idx]"
                ></p>
                <p class="text-xs text-slate-400">Memanggil Gemini — mohon tunggu beberapa detik.</p>
            </div>

            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="h-5 w-5 text-brand-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25" />
                    </svg>
                    Flashcard
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">Kartu belajar (depan-belakang) yang dibuat otomatis dari isi dokumen.</p>
            </div>

            @unless ($hasChunks)
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700">Flashcard belum bisa dibuat</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Kartu disusun dari potongan (chunk) dokumen. Klik
                        <span class="font-medium text-slate-700">"Proses ke Vector"</span> di atas terlebih dahulu.
                    </p>
                </div>
            @else
                {{-- Form pembuatan flashcard. --}}
                <div class="border-b border-slate-200 bg-slate-50/70 px-6 py-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="fc-count" class="text-xs font-medium text-slate-600">Jumlah kartu</label>
                            <select
                                id="fc-count"
                                wire:model="flashcardCount"
                                class="mt-1 block rounded-lg border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                            >
                                <option value="5">5 kartu</option>
                                <option value="10">10 kartu</option>
                                <option value="15">15 kartu</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            wire:click="generateFlashcards"
                            wire:loading.attr="disabled"
                            wire:target="generateFlashcards"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                            </svg>
                            {{ $this->flashcards->isEmpty() ? 'Buat Flashcard' : 'Tambah Kartu' }}
                        </button>
                    </div>
                    @error('flashcardCount') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Daftar kartu. --}}
                @if ($this->flashcards->isEmpty())
                    <div class="px-6 py-10 text-center">
                        <p class="text-sm font-medium text-slate-700">Belum ada flashcard</p>
                        <p class="mt-1 text-xs text-slate-500">Pilih jumlah kartu lalu klik "Buat Flashcard".</p>
                    </div>
                @else
                    <div class="px-6 py-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-medium text-slate-700">{{ $this->flashcards->count() }} kartu tersimpan</p>
                            <div class="flex items-center gap-1">
                                <a
                                    href="{{ route('flashcards.study', $d) }}"
                                    wire:navigate
                                    class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-brand-700"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                    </svg>
                                    Belajar Flashcard
                                </a>
                                <button
                                    type="button"
                                    wire:click="deleteFlashcards"
                                    wire:confirm="Hapus seluruh flashcard dokumen ini beserta riwayat belajarnya?"
                                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50"
                                >
                                    Hapus Semua
                                </button>
                            </div>
                        </div>
                        <ul class="max-h-64 space-y-1.5 overflow-y-auto">
                            @foreach ($this->flashcards as $i => $card)
                                <li wire:key="fc-{{ $card->id }}" class="flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2">
                                    <span class="w-5 flex-none text-right text-xs font-semibold tabular-nums text-slate-400">{{ $i + 1 }}</span>
                                    <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ $card->front_text }}</span>
                                    <span class="inline-flex flex-none items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset {{ $diffBadge[$card->difficulty] ?? '' }}">
                                        {{ $card->difficultyLabel() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endunless
        </div>

        {{-- ───────── Teks hasil ekstraksi ───────── --}}
        @php
            $extractedText = (string) ($d->extracted_text ?? '');
            $charCount = mb_strlen($extractedText);
            $wordCount = $extractedText === ''
                ? 0
                : count(preg_split('/\s+/u', trim($extractedText), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        @endphp
        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            x-data="{
                open: false,
                copied: false,
                copyText(el) {
                    const text = el.innerText;
                    const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000); };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(text).then(done).catch(() => {});
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        try { document.execCommand('copy'); done(); } catch (e) {}
                        document.body.removeChild(ta);
                    }
                },
            }"
        >
            <button
                type="button"
                x-on:click="open = ! open"
                class="flex w-full items-center justify-between gap-3 px-6 py-4 text-left transition hover:bg-slate-50/60"
            >
                <span class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-slate-900">Teks Hasil Ekstraksi</span>
                    @if ($charCount > 0)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                            {{ number_format($wordCount) }} kata
                        </span>
                    @endif
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 flex-none text-slate-400 transition" :class="open && 'rotate-180'">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition class="border-t border-slate-200 px-6 py-4">
                @if ($charCount === 0)
                    {{-- Empty state. --}}
                    <div class="py-8 text-center">
                        <span class="mx-auto inline-flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-medium text-slate-700">Belum ada teks hasil ekstraksi</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Dokumen ini belum diproses, atau teksnya tidak berhasil diekstrak dari PDF.
                        </p>
                    </div>
                @else
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-slate-500">
                            {{ number_format($charCount) }} karakter
                            <span class="mx-1 text-slate-300">·</span>
                            {{ number_format($wordCount) }} kata
                        </p>
                        <button
                            type="button"
                            x-on:click="copyText($refs.extractedText)"
                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            <svg x-show="! copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m11.25 5.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                            </svg>
                            <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 text-brand-600">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <span x-text="copied ? 'Tersalin' : 'Salin teks'"></span>
                        </button>
                    </div>
                    <div
                        x-ref="extractedText"
                        class="max-h-96 overflow-y-auto whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-700"
                    >{{ $extractedText }}</div>
                @endif
            </div>
        </div>

    </div>
</div>
