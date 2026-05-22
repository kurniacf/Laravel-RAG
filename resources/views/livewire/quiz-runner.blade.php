@php
    use App\Models\Quiz;
    use App\Models\QuizQuestion;

    $diffBadge = [
        Quiz::DIFFICULTY_EASY   => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        Quiz::DIFFICULTY_MEDIUM => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        Quiz::DIFFICULTY_HARD   => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    ];

    $quiz = $this->quiz;
    $questions = $this->questions;
@endphp

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl space-y-6">

        {{-- Kembali ke dokumen. --}}
        <a
            href="{{ route('documents.show', $quiz->document_id) }}"
            wire:navigate
            class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Kembali ke Dokumen
        </a>

        @if (session('status'))
            <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
        @endif

        {{-- ════════ MODE: OVERVIEW ════════ --}}
        @if ($mode === 'overview')
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-lg font-semibold tracking-tight text-slate-900">{{ $quiz->title }}</h1>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $diffBadge[$quiz->difficulty] ?? '' }}">
                            {{ $quiz->difficultyLabel() }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $quiz->question_count }} soal · campuran pilihan ganda, benar/salah, dan isian singkat.</p>
                </div>

                <dl class="grid grid-cols-3 gap-px border-b border-slate-200 bg-slate-200">
                    <div class="bg-white px-6 py-3">
                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Jumlah Soal</dt>
                        <dd class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums">{{ $quiz->question_count }}</dd>
                    </div>
                    <div class="bg-white px-6 py-3">
                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Dikerjakan</dt>
                        <dd class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums">{{ $quiz->total_attempts }}×</dd>
                    </div>
                    <div class="bg-white px-6 py-3">
                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Rata-rata</dt>
                        <dd class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums">{{ $quiz->average_score !== null ? $quiz->average_score : '—' }}</dd>
                    </div>
                </dl>

                <div class="px-6 py-5">
                    <button
                        type="button"
                        wire:click="startQuiz"
                        wire:loading.attr="disabled"
                        wire:target="startQuiz"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.344-5.891a1.5 1.5 0 0 0 0-2.538L6.3 2.841Z" />
                        </svg>
                        Mulai Kuis
                    </button>

                    @if ($this->pastAttempts->isNotEmpty())
                        <div class="mt-6">
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Riwayat Pengerjaan</h2>
                            <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200">
                                @foreach ($this->pastAttempts as $past)
                                    <li class="flex items-center justify-between px-4 py-2.5">
                                        <span class="text-sm text-slate-600">{{ $past->completed_at?->translatedFormat('d M Y, H:i') }}</span>
                                        <span class="text-sm font-semibold tabular-nums {{ $past->score >= 80 ? 'text-brand-700' : ($past->score >= 50 ? 'text-amber-700' : 'text-rose-700') }}">
                                            {{ $past->score }} · {{ $past->correct_count }}/{{ $past->total_questions }} benar
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

        {{-- ════════ MODE: TAKING ════════ --}}
        @elseif ($mode === 'taking')
            <form wire:submit="submitQuiz" class="space-y-5">
                <div class="rounded-xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
                    <h1 class="text-base font-semibold text-slate-900">{{ $quiz->title }}</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Jawab {{ $questions->count() }} soal di bawah. Soal yang dilewati dihitung salah.</p>
                </div>

                @foreach ($questions as $i => $q)
                    <div wire:key="q-{{ $q->id }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-7 w-7 flex-none items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $q->typeLabel() }}</span>
                                <p class="mt-0.5 text-sm font-medium leading-relaxed text-slate-900">{{ $q->question_text }}</p>

                                <div class="mt-3 space-y-2">
                                    @if ($q->isOptionBased())
                                        @foreach ($q->options as $opt)
                                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 transition hover:border-brand-300 hover:bg-brand-50/40 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                                <input
                                                    type="radio"
                                                    wire:model="answers.{{ $q->id }}"
                                                    value="{{ $opt->id }}"
                                                    class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500"
                                                >
                                                <span>{{ $opt->option_text }}</span>
                                            </label>
                                        @endforeach
                                    @else
                                        <input
                                            type="text"
                                            wire:model="answers.{{ $q->id }}"
                                            placeholder="Ketik jawaban singkatmu..."
                                            class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/30"
                                        >
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex items-center justify-end gap-3">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submitQuiz"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60"
                    >
                        <svg wire:loading wire:target="submitQuiz" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="submitQuiz">Kumpulkan Jawaban</span>
                        <span wire:loading wire:target="submitQuiz">Menilai...</span>
                    </button>
                </div>
            </form>

        {{-- ════════ MODE: RESULT ════════ --}}
        @else
            @php
                $attempt = $this->attempt;
                $answersByQ = $attempt?->answers->keyBy('quiz_question_id') ?? collect();
                $score = (int) ($attempt->score ?? 0);
                // Kelas Tailwind ditulis lengkap (bukan dirakit dinamis) agar terdeteksi build.
                $scoreBg = $score >= 80 ? 'bg-brand-50' : ($score >= 50 ? 'bg-amber-50' : 'bg-rose-50');
                $scoreText = $score >= 80 ? 'text-brand-700' : ($score >= 50 ? 'text-amber-700' : 'text-rose-700');
                $secs = (int) ($attempt->time_spent_seconds ?? 0);
                $timeLabel = $secs >= 60 ? intdiv($secs, 60).' mnt '.($secs % 60).' dtk' : $secs.' detik';
                $suggested = $score >= 80 ? Quiz::DIFFICULTY_HARD : ($score >= 50 ? Quiz::DIFFICULTY_MEDIUM : Quiz::DIFFICULTY_EASY);
            @endphp

            {{-- Kartu skor. --}}
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col items-center gap-2 {{ $scoreBg }} px-6 py-8 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider {{ $scoreText }}">Skor Kamu</p>
                    <p class="text-5xl font-bold tracking-tight {{ $scoreText }} tabular-nums">{{ $score }}</p>
                    <p class="text-sm font-medium text-slate-600">
                        {{ $attempt?->correct_count }} dari {{ $attempt?->total_questions }} soal benar
                        <span class="mx-1 text-slate-300">·</span>
                        {{ $timeLabel }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-3 px-6 py-4">
                    <button
                        type="button"
                        wire:click="retakeQuiz"
                        wire:loading.attr="disabled"
                        wire:target="retakeQuiz"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Ulangi Kuis
                    </button>
                    <button
                        type="button"
                        wire:click="generateFollowUp"
                        wire:loading.attr="disabled"
                        wire:target="generateFollowUp"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60"
                        title="Buat kuis baru dari dokumen ini dengan tingkat yang disesuaikan"
                    >
                        <svg wire:loading wire:target="generateFollowUp" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4l3-3-3-3v4a8 8 0 1 0 8 8h-4l3 3 3-3h-4a8 8 0 0 1-8 8z"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="generateFollowUp" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                        </svg>
                        <span wire:loading.remove wire:target="generateFollowUp">Kuis lanjutan ({{ Quiz::difficultyLabelFor($suggested) }})</span>
                        <span wire:loading wire:target="generateFollowUp">Menyusun...</span>
                    </button>
                </div>
                <p class="px-6 pb-4 text-center text-xs text-slate-400">
                    Rekomendasi adaptif: berdasarkan skormu, kuis lanjutan disetel ke tingkat
                    <span class="font-medium text-slate-600">{{ Quiz::difficultyLabelFor($suggested) }}</span>.
                </p>
            </div>

            {{-- Review per soal. --}}
            <div class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900">Pembahasan Jawaban</h2>
                @foreach ($questions as $i => $q)
                    @php
                        $ans = $answersByQ->get($q->id);
                        $correct = (bool) ($ans?->is_correct);
                    @endphp
                    <div wire:key="rev-{{ $q->id }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-7 w-7 flex-none items-center justify-center rounded-full text-xs font-semibold {{ $correct ? 'bg-brand-100 text-brand-700' : 'bg-rose-100 text-rose-700' }}">
                                @if ($correct)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Soal {{ $i + 1 }} · {{ $q->typeLabel() }}</span>
                                <p class="mt-0.5 text-sm font-medium leading-relaxed text-slate-900">{{ $q->question_text }}</p>

                                <div class="mt-3 space-y-1.5">
                                    @if ($q->isOptionBased())
                                        @foreach ($q->options as $opt)
                                            @php
                                                $isPicked = $ans && (int) $ans->selected_option_id === $opt->id;
                                                $tone = $opt->is_correct
                                                    ? 'border-brand-300 bg-brand-50 text-brand-800'
                                                    : ($isPicked ? 'border-rose-300 bg-rose-50 text-rose-800' : 'border-slate-200 text-slate-600');
                                            @endphp
                                            <div class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm {{ $tone }}">
                                                <span class="flex-1">{{ $opt->option_text }}</span>
                                                @if ($opt->is_correct)
                                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-brand-600">Jawaban benar</span>
                                                @elseif ($isPicked)
                                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-rose-600">Pilihanmu</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="rounded-lg border px-3 py-2 text-sm {{ $correct ? 'border-brand-300 bg-brand-50 text-brand-800' : 'border-rose-300 bg-rose-50 text-rose-800' }}">
                                            <span class="font-medium">Jawabanmu:</span> {{ $ans?->answer_text ?: '(kosong)' }}
                                        </p>
                                        @unless ($correct)
                                            <p class="rounded-lg border border-brand-300 bg-brand-50 px-3 py-2 text-sm text-brand-800">
                                                <span class="font-medium">Jawaban benar:</span> {{ $q->correct_answer }}
                                            </p>
                                        @endunless
                                    @endif
                                </div>

                                @if ($q->explanation)
                                    <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2.5">
                                        <p class="text-xs font-semibold text-slate-500">Penjelasan</p>
                                        <p class="mt-0.5 text-sm leading-relaxed text-slate-700">{{ $q->explanation }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</div>
