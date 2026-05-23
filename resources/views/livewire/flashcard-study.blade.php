@php
    $diffBadge = [
        'easy'   => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'medium' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'hard'   => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    ];
    $total = count($queue);
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl space-y-6">

        {{-- Kembali ke dokumen. --}}
        <a
            href="{{ route('documents.show', $document) }}"
            wire:navigate
            class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Kembali ke Dokumen
        </a>

        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Belajar Flashcard</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-900">{{ $document->title }}</h1>
        </div>

        {{-- ════════ MODE: EMPTY ════════ --}}
        @if ($mode === 'empty')
            <div class="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
                @if ($this->totalCards === 0)
                    <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-7 w-7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25" />
                        </svg>
                    </span>
                    <p class="mt-4 text-sm font-semibold text-slate-800">Dokumen ini belum punya flashcard</p>
                    <p class="mt-1 text-sm text-slate-500">Buat flashcard dulu di halaman detail dokumen.</p>
                    <a
                        href="{{ route('documents.show', $document) }}"
                        wire:navigate
                        class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                    >
                        Buat Flashcard
                    </a>
                @else
                    <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-7 w-7">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <p class="mt-4 text-sm font-semibold text-slate-800">Tidak ada kartu yang jatuh tempo</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Semua kartu sudah dijadwalkan.
                        @php $next = $this->nextReviewAt(); @endphp
                        @if ($next)
                            Review berikutnya {{ $next->diffForHumans() }}.
                        @endif
                    </p>
                    <button
                        type="button"
                        wire:click="studyAll"
                        class="mt-5 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Pelajari Semua Lagi
                    </button>
                @endif
            </div>

        {{-- ════════ MODE: DONE ════════ --}}
        @elseif ($mode === 'done')
            @php $next = $this->nextReviewAt(); @endphp
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col items-center gap-2 bg-brand-50 px-6 py-8 text-center">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <p class="text-lg font-semibold text-brand-800">Sesi belajar selesai!</p>
                    <p class="text-sm text-brand-700">Kerja bagus — terus konsisten ya.</p>
                </div>

                <dl class="grid grid-cols-2 gap-px border-y border-slate-200 bg-slate-200">
                    <div class="bg-white px-6 py-4 text-center">
                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kartu Direview</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $reviewedCount }}</dd>
                    </div>
                    <div class="bg-white px-6 py-4 text-center">
                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kartu Dikuasai</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $masteredCount }}</dd>
                    </div>
                </dl>

                <div class="px-6 py-5 text-center">
                    @if ($next)
                        <p class="text-sm text-slate-600">
                            Review berikutnya dijadwalkan
                            <span class="font-semibold text-slate-900">{{ $next->diffForHumans() }}</span>.
                        </p>
                    @else
                        <p class="text-sm text-slate-600">Semua kartu sudah kamu pelajari.</p>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                        <button
                            type="button"
                            wire:click="studyAll"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Pelajari Semua Lagi
                        </button>
                        <a
                            href="{{ route('documents.show', $document) }}"
                            wire:navigate
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-700"
                        >
                            Kembali ke Dokumen
                        </a>
                    </div>
                </div>
            </div>

        {{-- ════════ MODE: STUDY ════════ --}}
        @else
            @php $card = $this->currentCard; @endphp
            @if ($card)
                {{-- Progres. --}}
                <div>
                    <div class="flex items-center justify-between text-xs font-medium text-slate-500">
                        <span>Kartu {{ $position + 1 }} dari {{ $total }}</span>
                        <span>{{ $total - $position }} kartu tersisa</span>
                    </div>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-brand-500 transition-all duration-300" style="width: {{ $total > 0 ? round($position / $total * 100) : 0 }}%"></div>
                    </div>
                </div>

                {{-- Kartu flip. --}}
                <div
                    wire:key="card-{{ $card->id }}"
                    @class(['flashcard', 'is-flipped' => $flipped])
                >
                    <div class="flashcard-inner min-h-[20rem]">
                        {{-- Sisi depan. --}}
                        <div
                            @if (! $flipped) wire:click="flip" @endif
                            class="flashcard-face flex min-h-[20rem] cursor-pointer flex-col items-center justify-center gap-4 rounded-2xl border border-brand-200 bg-brand-50 p-8 text-center"
                        >
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider ring-1 ring-inset {{ $diffBadge[$card->difficulty] ?? '' }}">
                                {{ $card->difficultyLabel() }}
                            </span>
                            <p class="text-lg font-semibold leading-relaxed text-slate-900">{{ $card->front_text }}</p>
                            <p class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-6 6m0 0v-4.8m0 4.8h4.8M21 9l-6-6m0 0v4.8M15 3h-4.8M3 15l6 6m6-6l6 6" />
                                </svg>
                                Klik kartu untuk lihat jawaban
                            </p>
                        </div>
                        {{-- Sisi belakang. --}}
                        <div class="flashcard-back flashcard-face flex min-h-[20rem] flex-col items-center justify-center gap-3 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-8 text-center">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Jawaban</span>
                            <p class="text-base leading-relaxed text-slate-800">{{ $card->back_text }}</p>
                        </div>
                    </div>
                </div>

                {{-- Aksi: sebelum flip vs sesudah flip. --}}
                @if (! $flipped)
                    <div class="text-center">
                        <button
                            type="button"
                            wire:click="flip"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                        >
                            Lihat Jawaban
                        </button>
                    </div>
                @else
                    <div>
                        <p class="mb-2 text-center text-xs font-medium text-slate-500">Seberapa baik kamu menguasai kartu ini?</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                wire:click="rate('hard')"
                                class="flex flex-col items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                            >
                                Sulit
                                <span class="text-[10px] font-normal text-rose-500">Ulangi besok</span>
                            </button>
                            <button
                                type="button"
                                wire:click="rate('good')"
                                class="flex flex-col items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm font-semibold text-amber-700 transition hover:bg-amber-100"
                            >
                                Cukup
                                <span class="text-[10px] font-normal text-amber-600">Jadwal normal</span>
                            </button>
                            <button
                                type="button"
                                wire:click="rate('easy')"
                                class="flex flex-col items-center gap-1 rounded-lg border border-brand-200 bg-brand-50 px-3 py-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-100"
                            >
                                Mudah
                                <span class="text-[10px] font-normal text-brand-600">Tunda lebih lama</span>
                            </button>
                        </div>
                    </div>
                @endif
            @endif
        @endif

    </div>
</div>
