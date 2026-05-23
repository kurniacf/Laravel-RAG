@extends('pdf.layout', ['title' => 'Kuis · '.$quiz->title])

@php
    use App\Models\QuizQuestion;

    /** @var \App\Models\Quiz $quiz */
    /** @var bool $withAnswerKey */

    $document = $quiz->document;

    // Huruf opsi untuk MCQ: A, B, C, D, ...
    $optionLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
@endphp

@section('content')

    <div class="doc-header">
        <div class="brand">Lembar Kuis</div>
        <h1>{{ $quiz->title }}</h1>
        <div class="meta">
            @if ($document?->subject)
                <span class="subject-pill" style="background-color: {{ $document->subject->color_hex }}1a; color: {{ $document->subject->color_hex }};">
                    {{ $document->subject->name }}
                </span>
                &nbsp;
            @endif
            <strong>Dokumen:</strong> {{ $document?->title ?? '—' }}
            <br>
            <strong>Tingkat Kesulitan:</strong> {{ $quiz->difficultyLabel() }}
            &nbsp;·&nbsp;
            <strong>Jumlah Soal:</strong> {{ $quiz->question_count }}
            &nbsp;·&nbsp;
            <strong>Diunduh:</strong> {{ $generatedAt->format('d F Y, H:i') }}
        </div>
    </div>

    <h2>Soal</h2>

    @foreach ($quiz->questions as $i => $q)
        @php($num = $i + 1)
        <div class="question">
            <div class="q-head">{{ $num }}. {{ $q->question_text }}</div>

            @if ($q->type === QuizQuestion::TYPE_MCQ)
                <ol class="options" type="A">
                    @foreach ($q->options as $opt)
                        <li>{{ $opt->option_text }}</li>
                    @endforeach
                </ol>
            @elseif ($q->type === QuizQuestion::TYPE_TRUE_FALSE)
                <ol class="options" type="A">
                    @foreach ($q->options as $opt)
                        <li>{{ $opt->option_text }}</li>
                    @endforeach
                </ol>
            @else
                {{-- short_answer: kotak isian kosong --}}
                <div class="answer-box">Jawaban: ____________________________________________</div>
            @endif

            <div class="q-meta">Jenis: {{ $q->typeLabel() }}</div>
        </div>
    @endforeach

    @if ($withAnswerKey)
        <div class="key-section">
            <h2>Kunci Jawaban &amp; Pembahasan</h2>

            @foreach ($quiz->questions as $i => $q)
                @php($num = $i + 1)
                <div class="question">
                    <div class="q-head">{{ $num }}. {{ $q->question_text }}</div>

                    @if ($q->isOptionBased())
                        {{-- Iterasi opsi: cetak yang is_correct. Indeks loop dipakai untuk
                             huruf A/B/C/D — lebih bersih daripada Collection::search()
                             di dalam @php block (yang membingungkan Blade compiler bila
                             pakai arrow function). --}}
                        @foreach ($q->options as $oi => $opt)
                            @if ($opt->is_correct)
                                <p><strong>Jawaban:</strong> <span class="correct">{{ $optionLetters[$oi] ?? '' }}. {{ $opt->option_text }}</span></p>
                            @endif
                        @endforeach
                    @else
                        <p><strong>Jawaban:</strong> <span class="correct">{{ $q->correct_answer }}</span></p>
                    @endif

                    @if (! empty($q->explanation))
                        <div class="explanation">
                            <strong>Pembahasan:</strong><br>
                            {{ $q->explanation }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (! $withAnswerKey)
        <p class="ai-note" style="margin-top: 6mm;">
            Lembar soal ini siap dicetak dan dikerjakan. Untuk versi dengan kunci jawaban
            dan pembahasan, unduh ulang dengan opsi "Lengkap dengan kunci".
        </p>
    @endif

@endsection
