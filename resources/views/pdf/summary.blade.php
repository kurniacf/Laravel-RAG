@extends('pdf.layout', ['title' => 'Ringkasan · '.$document->title])

@php
    use App\Models\Summary;

    /** @var \App\Models\Document $document */
    /** @var \Illuminate\Support\Collection<string, \App\Models\Summary> $summaries */
    $executive = $summaries->get(Summary::TYPE_EXECUTIVE);
    $perChapter = $summaries->get(Summary::TYPE_PER_CHAPTER);
    $keyPoints = $summaries->get(Summary::TYPE_KEY_POINTS);
@endphp

@section('content')

    <div class="doc-header">
        <div class="brand">Ringkasan Dokumen</div>
        <h1>{{ $document->title }}</h1>
        <div class="meta">
            @if ($document->subject)
                <span class="subject-pill" style="background-color: {{ $document->subject->color_hex }}1a; color: {{ $document->subject->color_hex }};">
                    {{ $document->subject->name }}
                </span>
                &nbsp;
            @endif
            <strong>Berkas:</strong> {{ $document->original_filename }}
            @if ($document->page_count)
                &nbsp;·&nbsp; {{ $document->page_count }} halaman
            @endif
            <br>
            <strong>Pemilik:</strong> {{ $document->user?->name ?? '—' }}
            &nbsp;·&nbsp;
            <strong>Diunduh:</strong> {{ $generatedAt->format('d F Y, H:i') }}
        </div>
    </div>

    @if ($executive)
        <h2>Ringkasan Eksekutif</h2>
        <div class="section-body">{{ $executive->content }}</div>
    @endif

    @if ($perChapter)
        <h2>Ringkasan Per Bagian</h2>
        @php($sections = $perChapter->sections())
        @if (count($sections) === 0)
            <div class="section-body">{{ $perChapter->content }}</div>
        @else
            @foreach ($sections as $section)
                @if (! empty($section['title']))
                    <h3>{{ $section['title'] }}</h3>
                @endif
                <div class="section-body">{{ $section['body'] }}</div>
            @endforeach
        @endif
    @endif

    @if ($keyPoints)
        <h2>Poin Kunci</h2>
        @php($points = $keyPoints->points())
        @if (count($points) === 0)
            <div class="section-body">{{ $keyPoints->content }}</div>
        @else
            <ul class="points">
                @foreach ($points as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    <p class="ai-note">
        Ringkasan ini dihasilkan secara otomatis oleh model Google Gemini berdasarkan
        teks dokumen Anda. Mohon verifikasi sebelum digunakan untuk keputusan penting.
    </p>

@endsection
