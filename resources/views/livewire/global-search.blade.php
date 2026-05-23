@php
    /**
     * Kotak pencarian global di topbar. Dropdown hasil dirender kondisional
     * berdasarkan `open` (Alpine) — flag itu juga kita sinkronkan ke server
     * via $wire.set bila perlu, tapi sebagian besar interaksi (focus/blur,
     * navigasi keyboard) cukup dijalankan di klien.
     */
    $isReady = $this->isQueryReady();
    $documents = $this->documents;
    $subjects = $this->subjects;
    $quizzes = $this->quizzes;
    $totalResults = $this->totalResults;
@endphp

<div
    class="relative w-full max-w-md"
    x-data="{
        open: @entangle('open').live,
        onSubmit() {
            // Tekan Enter: bila ada satu hasil dokumen, langsung navigasi.
            // Selain itu biarkan dropdown saja.
        },
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </span>
        <input
            type="search"
            wire:model.live.debounce.300ms="query"
            @focus="open = true"
            placeholder="Cari dokumen, mata pelajaran, kuis..."
            aria-label="Pencarian global"
            class="block w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-9 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-brand-600 focus:bg-white focus:ring-2 focus:ring-brand-600/30"
        >

        {{-- Tombol bersihkan (muncul saat ada teks). --}}
        @if ($query !== '')
            <button
                type="button"
                wire:click="clear"
                class="absolute inset-y-0 right-2 my-auto inline-flex h-6 w-6 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Bersihkan pencarian"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        @endif

        {{-- Loading spinner overlay saat Livewire round-trip. --}}
        <span
            wire:loading
            wire:target="query"
            class="absolute inset-y-0 right-2 my-auto inline-flex h-6 w-6 items-center justify-center text-brand-600"
        >
            <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
            </svg>
        </span>
    </div>

    {{-- Dropdown hasil. --}}
    <div
        x-show="open && {{ $isReady ? 'true' : 'false' }}"
        x-cloak
        x-transition.opacity.duration.100ms
        class="absolute left-0 right-0 z-40 mt-1 max-h-[28rem] overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl"
    >
        @if ($totalResults === 0)
            <div class="px-4 py-6 text-center">
                <p class="text-sm font-medium text-slate-700">Tidak ditemukan hasil</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    untuk <span class="font-medium text-slate-700">"{{ $query }}"</span>. Coba kata kunci lain.
                </p>
            </div>
        @else
            {{-- Dokumen. --}}
            @if ($documents->isNotEmpty())
                <div class="border-b border-slate-100 last:border-0">
                    <div class="flex items-center justify-between px-3 py-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Dokumen</span>
                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 tabular-nums">{{ $documents->count() }}</span>
                    </div>
                    <ul>
                        @foreach ($documents as $doc)
                            <li>
                                <a
                                    href="{{ route('documents.show', $doc) }}"
                                    wire:navigate
                                    wire:click="close"
                                    class="flex items-start gap-3 px-3 py-2 transition hover:bg-slate-50"
                                >
                                    <span class="mt-0.5 inline-flex h-7 w-7 flex-none items-center justify-center rounded-md bg-slate-100 text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $doc->title }}</p>
                                        <p class="truncate text-xs text-slate-500">
                                            {{ $doc->original_filename }}
                                            @if ($doc->subject)
                                                <span class="mx-1 text-slate-300">·</span>
                                                <span class="font-medium" style="color: {{ $doc->subject->color_hex }};">{{ $doc->subject->name }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Mata pelajaran. --}}
            @if ($subjects->isNotEmpty())
                <div class="border-b border-slate-100 last:border-0">
                    <div class="flex items-center justify-between px-3 py-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Mata Pelajaran</span>
                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 tabular-nums">{{ $subjects->count() }}</span>
                    </div>
                    <ul>
                        @foreach ($subjects as $subject)
                            <li>
                                <a
                                    href="{{ route('subjects.index', ['q' => $subject->name]) }}"
                                    wire:navigate
                                    wire:click="close"
                                    class="flex items-start gap-3 px-3 py-2 transition hover:bg-slate-50"
                                >
                                    <span
                                        class="mt-0.5 inline-flex h-7 w-7 flex-none items-center justify-center rounded-md"
                                        style="background-color: {{ $subject->color_hex }}1a; color: {{ $subject->color_hex }};"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $subject->name }}</p>
                                        <p class="truncate text-xs text-slate-500">
                                            {{ $subject->documents_count }} dokumen
                                        </p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Kuis. --}}
            @if ($quizzes->isNotEmpty())
                <div class="border-b border-slate-100 last:border-0">
                    <div class="flex items-center justify-between px-3 py-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Kuis</span>
                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 tabular-nums">{{ $quizzes->count() }}</span>
                    </div>
                    <ul>
                        @foreach ($quizzes as $quiz)
                            <li>
                                <a
                                    href="{{ route('quizzes.show', $quiz) }}"
                                    wire:navigate
                                    wire:click="close"
                                    class="flex items-start gap-3 px-3 py-2 transition hover:bg-slate-50"
                                >
                                    <span class="mt-0.5 inline-flex h-7 w-7 flex-none items-center justify-center rounded-md bg-emerald-50 text-emerald-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $quiz->title }}</p>
                                        <p class="truncate text-xs text-slate-500">
                                            {{ $quiz->question_count }} soal
                                            <span class="mx-1 text-slate-300">·</span>
                                            {{ \App\Models\Quiz::difficultyLabelFor($quiz->difficulty) }}
                                            @if ($quiz->document)
                                                <span class="mx-1 text-slate-300">·</span>
                                                dari "{{ \Illuminate\Support\Str::limit($quiz->document->title, 28) }}"
                                            @endif
                                        </p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        {{-- Footer dropdown: hint. --}}
        <div class="border-t border-slate-100 bg-slate-50 px-3 py-1.5 text-[10px] text-slate-500">
            Ketik minimal {{ \App\Livewire\GlobalSearch::MIN_QUERY_LENGTH }} karakter ·
            Tekan <kbd class="rounded border border-slate-300 bg-white px-1 font-mono">Esc</kbd> untuk tutup
        </div>
    </div>
</div>
