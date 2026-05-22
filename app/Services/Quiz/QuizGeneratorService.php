<?php

namespace App\Services\Quiz;

use App\Models\AiJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Services\Rag\GeminiChatService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Menghasilkan kuis dari sebuah dokumen.
 *
 * Alur: sampling chunk dokumen → prompt Gemini untuk JSON terstruktur →
 * parse aman + retry → validasi tiap soal → simpan ke quizzes / quiz_questions
 * / quiz_options. Dicatat ke ai_jobs (job_type quiz_gen).
 *
 * Soal dibuat dari `document_chunks` (bukan teks penuh) supaya tiap soal bisa
 * ditelusuri ke chunk asalnya lewat `source_chunk_id`.
 */
class QuizGeneratorService
{
    /** Maksimal percobaan ulang bila JSON dari Gemini gagal di-parse/divalidasi. */
    public const MAX_RETRIES = 2;

    public const MIN_QUESTIONS = 3;

    public const MAX_QUESTIONS = 15;

    /** Batas jumlah chunk yang dikirim sebagai sumber (kendali ukuran prompt). */
    public const MAX_SOURCE_CHUNKS = 12;

    public function __construct(protected GeminiChatService $chat) {}

    /**
     * Buat satu kuis dari dokumen.
     *
     * @throws RuntimeException bila input tidak valid atau JSON gagal diproses.
     */
    public function generate(Document $document, int $questionCount, string $difficulty): Quiz
    {
        if (! in_array($difficulty, Quiz::DIFFICULTIES, true)) {
            throw new RuntimeException("Tingkat kesulitan tidak dikenal: {$difficulty}");
        }

        $questionCount = max(self::MIN_QUESTIONS, min(self::MAX_QUESTIONS, $questionCount));

        $chunks = $this->sampleChunks($document);

        if ($chunks->isEmpty()) {
            throw new RuntimeException(
                'Dokumen belum punya chunk. Jalankan "Proses ke Vector" sebelum membuat kuis.'
            );
        }

        $job = AiJob::create([
            'document_id' => $document->id,
            'job_type' => AiJob::TYPE_QUIZ_GEN,
            'status' => AiJob::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $start = microtime(true);
        $totalTokens = 0;

        try {
            $questions = [];
            $attempt = 0;

            // Retry: Gemini kadang mengembalikan JSON cacat. Coba ulang sampai
            // dapat minimal satu soal valid atau kuota percobaan habis.
            do {
                $attempt++;

                $result = $this->chat->generate(
                    $this->systemInstruction(),
                    $this->userMessage($questionCount, $difficulty, $chunks),
                    [
                        'temperature' => 0.4,
                        'max_output_tokens' => 4096,
                        'response_mime_type' => 'application/json',
                    ],
                );

                $totalTokens += (int) $result['tokens_used'];
                $questions = $this->parseAndValidate($result['answer'], $difficulty, $chunks);
            } while (empty($questions) && $attempt <= self::MAX_RETRIES);

            if (empty($questions)) {
                throw new RuntimeException(
                    "Gagal mendapatkan soal kuis yang valid dari Gemini setelah {$attempt} percobaan."
                );
            }

            $quiz = $this->persist(
                $document,
                $difficulty,
                array_slice($questions, 0, $questionCount),
            );

            $job->update([
                'status' => AiJob::STATUS_COMPLETED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'tokens_used' => $totalTokens,
            ]);

            return $quiz;
        } catch (Throwable $e) {
            $job->update([
                'status' => AiJob::STATUS_FAILED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'tokens_used' => $totalTokens,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Ambil chunk sumber soal. Bila chunk terlalu banyak, ambil sampel merata
     * sepanjang dokumen agar soal mencakup keseluruhan materi.
     *
     * @return Collection<int, DocumentChunk>
     */
    protected function sampleChunks(Document $document): Collection
    {
        $chunks = DocumentChunk::where('document_id', $document->id)
            ->orderBy('chunk_index')
            ->get(['id', 'chunk_index', 'content']);

        if ($chunks->count() <= self::MAX_SOURCE_CHUNKS) {
            return $chunks->values();
        }

        $step = $chunks->count() / self::MAX_SOURCE_CHUNKS;
        $sampled = collect();
        for ($i = 0; $i < self::MAX_SOURCE_CHUNKS; $i++) {
            $sampled->push($chunks[(int) floor($i * $step)]);
        }

        return $sampled->unique('id')->values();
    }

    /**
     * Parse teks JSON dari Gemini lalu validasi tiap soal.
     *
     * @return array<int, array<string, mixed>> daftar soal yang lolos validasi.
     */
    protected function parseAndValidate(string $raw, string $difficulty, Collection $chunks): array
    {
        $json = $this->extractJson($raw);
        if ($json === null) {
            return [];
        }

        $rawQuestions = $json['questions'] ?? (array_is_list($json) ? $json : []);
        if (! is_array($rawQuestions)) {
            return [];
        }

        $valid = [];
        foreach ($rawQuestions as $q) {
            $normalized = $this->validateQuestion($q, $difficulty, $chunks);
            if ($normalized !== null) {
                $valid[] = $normalized;
            }
        }

        return $valid;
    }

    /**
     * Ekstrak objek JSON dari teks model. Membuang pembungkus markdown fence
     * (```json ... ```) bila ada, lalu decode dengan aman.
     */
    protected function extractJson(string $raw): ?array
    {
        $text = trim($raw);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text) ?? $text;
            $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;
            $text = trim($text);
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Validasi & normalisasi satu soal. Return null bila tidak valid.
     *
     * @return array<string, mixed>|null
     */
    protected function validateQuestion(mixed $q, string $difficulty, Collection $chunks): ?array
    {
        if (! is_array($q)) {
            return null;
        }

        $type = is_string($q['type'] ?? null) ? trim($q['type']) : '';
        $questionText = is_string($q['question_text'] ?? null) ? trim($q['question_text']) : '';

        if (! in_array($type, QuizQuestion::TYPES, true) || $questionText === '') {
            return null;
        }

        $base = [
            'type' => $type,
            'question_text' => $questionText,
            'explanation' => is_string($q['explanation'] ?? null) ? trim($q['explanation']) : '',
            'difficulty' => in_array($q['difficulty'] ?? null, Quiz::DIFFICULTIES, true)
                ? $q['difficulty']
                : $difficulty,
            'source_chunk_id' => $this->resolveSourceChunkId($q['source_chunk'] ?? null, $chunks),
        ];

        return match ($type) {
            QuizQuestion::TYPE_MCQ => $this->buildMcq($q, $base),
            QuizQuestion::TYPE_TRUE_FALSE => $this->buildTrueFalse($q, $base),
            QuizQuestion::TYPE_SHORT_ANSWER => $this->buildShortAnswer($q, $base),
            default => null,
        };
    }

    /**
     * Petakan nomor source_chunk (1-based) ke id chunk yang sebenarnya.
     */
    protected function resolveSourceChunkId(mixed $sourceChunk, Collection $chunks): ?int
    {
        if (! is_numeric($sourceChunk)) {
            return null;
        }

        $idx = (int) $sourceChunk - 1;

        return ($idx >= 0 && $idx < $chunks->count())
            ? (int) $chunks[$idx]->id
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildMcq(array $q, array $base): ?array
    {
        if (! is_array($q['options'] ?? null)) {
            return null;
        }

        $options = array_values(array_unique(array_filter(
            array_map(fn ($o) => is_string($o) ? trim($o) : '', $q['options']),
            fn ($o) => $o !== '',
        )));

        if (count($options) < 2) {
            return null;
        }

        $correct = is_string($q['correct_answer'] ?? null) ? trim($q['correct_answer']) : '';
        $correctIdx = $this->matchOption($correct, $options);
        if ($correctIdx === null) {
            return null;
        }

        $correctText = $options[$correctIdx];

        // Acak posisi opsi supaya jawaban benar tidak selalu di urutan pertama.
        shuffle($options);

        $base['correct_answer'] = $correctText;
        $base['options'] = [];
        foreach ($options as $i => $text) {
            $base['options'][] = [
                'option_text' => $text,
                'is_correct' => $text === $correctText,
                'position' => $i,
            ];
        }

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildTrueFalse(array $q, array $base): ?array
    {
        $ca = $q['correct_answer'] ?? null;
        $isTrue = null;

        if (is_bool($ca)) {
            $isTrue = $ca;
        } elseif (is_string($ca)) {
            $n = $this->normalize($ca);
            if (in_array($n, ['benar', 'true', 'betul', 'ya', 'b'], true)) {
                $isTrue = true;
            } elseif (in_array($n, ['salah', 'false', 'tidak', 's'], true)) {
                $isTrue = false;
            }
        }

        if ($isTrue === null) {
            return null;
        }

        $base['correct_answer'] = $isTrue ? 'Benar' : 'Salah';
        $base['options'] = [
            ['option_text' => 'Benar', 'is_correct' => $isTrue, 'position' => 0],
            ['option_text' => 'Salah', 'is_correct' => ! $isTrue, 'position' => 1],
        ];

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildShortAnswer(array $q, array $base): ?array
    {
        $correct = is_string($q['correct_answer'] ?? null) ? trim($q['correct_answer']) : '';
        if ($correct === '') {
            return null;
        }

        $base['correct_answer'] = $correct;
        $base['options'] = [];

        return $base;
    }

    /**
     * Cari indeks opsi yang cocok dengan jawaban benar. Toleran terhadap
     * perbedaan kapitalisasi/tanda baca, juga label huruf "A"-"D".
     */
    protected function matchOption(string $needle, array $options): ?int
    {
        $norm = $this->normalize($needle);
        if ($norm === '') {
            return null;
        }

        foreach ($options as $i => $opt) {
            if ($this->normalize($opt) === $norm) {
                return $i;
            }
        }

        // Model kadang menjawab dengan label huruf opsi saja ("B").
        if (preg_match('/^[a-d]$/', $norm)) {
            $idx = ord($norm) - ord('a');

            return $idx < count($options) ? $idx : null;
        }

        return null;
    }

    /** Normalisasi teks untuk perbandingan: huruf kecil, tanpa tanda baca. */
    protected function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', '', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /**
     * Simpan kuis beserta soal dan opsinya dalam satu transaksi.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    protected function persist(Document $document, string $difficulty, array $questions): Quiz
    {
        return DB::transaction(function () use ($document, $difficulty, $questions) {
            $quiz = Quiz::create([
                'document_id' => $document->id,
                'user_id' => $document->user_id,
                'title' => 'Kuis '.Quiz::difficultyLabelFor($difficulty).' — '.$document->title,
                'difficulty' => $difficulty,
                'question_count' => count($questions),
            ]);

            foreach ($questions as $pos => $q) {
                $question = QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'source_chunk_id' => $q['source_chunk_id'],
                    'type' => $q['type'],
                    'question_text' => $q['question_text'],
                    'correct_answer' => $q['correct_answer'],
                    'explanation' => $q['explanation'],
                    'difficulty' => $q['difficulty'],
                    'position' => $pos,
                ]);

                foreach ($q['options'] as $opt) {
                    QuizOption::create([
                        'quiz_question_id' => $question->id,
                        'option_text' => $opt['option_text'],
                        'is_correct' => $opt['is_correct'],
                        'position' => $opt['position'],
                    ]);
                }
            }

            return $quiz->load('questions.options');
        });
    }

    protected function systemInstruction(): string
    {
        return <<<'TXT'
        Kamu adalah asisten akademik PintarBelajar AI yang menyusun soal kuis
        dari materi belajar untuk pelajar Indonesia.

        ATURAN WAJIB:
        1. Susun soal HANYA berdasarkan isi materi pada blok [CHUNK n].
           DILARANG mengarang fakta, angka, atau contoh di luar materi.
        2. Penjelasan (explanation) tiap soal harus merujuk konsep yang
           benar-benar ada di materi.
        3. Tulis soal, pilihan jawaban, dan penjelasan dalam Bahasa Indonesia baku.
        4. Keluarkan HANYA JSON valid sesuai skema yang diminta — tanpa teks
           pembuka, tanpa komentar.
        TXT;
    }

    protected function userMessage(int $count, string $difficulty, Collection $chunks): string
    {
        $context = $chunks->values()
            ->map(function ($c, $i) {
                $n = $i + 1;

                return "[CHUNK {$n}]\n".trim((string) $c->content)."\n[/CHUNK {$n}]";
            })
            ->implode("\n\n");

        $difficultyHint = match ($difficulty) {
            Quiz::DIFFICULTY_EASY => 'mudah (mengingat fakta dan definisi langsung)',
            Quiz::DIFFICULTY_HARD => 'sulit (analisis, penerapan, dan hubungan antar-konsep)',
            default => 'sedang (pemahaman konsep inti)',
        };

        $schema = '{"questions":[{'
            .'"type":"mcq | true_false | short_answer",'
            .'"question_text":"teks pertanyaan",'
            .'"options":["opsi 1","opsi 2","opsi 3","opsi 4"],'
            .'"correct_answer":"jawaban benar",'
            .'"explanation":"penjelasan singkat mengapa jawaban itu benar",'
            .'"difficulty":"'.$difficulty.'",'
            .'"source_chunk":1'
            .'}]}';

        return implode("\n", [
            "Buat {$count} soal kuis dengan tingkat kesulitan {$difficultyHint} dari materi di bawah.",
            '',
            'Variasikan tipe soal: mayoritas "mcq", beberapa "true_false", dan 1-2 "short_answer" bila jumlah soal memungkinkan.',
            '',
            'Keluarkan JSON dengan struktur PERSIS seperti contoh ini:',
            $schema,
            '',
            'Ketentuan field:',
            '- "options": tepat 4 opsi untuk "mcq"; untuk "true_false" dan "short_answer" beri array kosong [].',
            '- "correct_answer": untuk "mcq" salin teks salah satu opsi PERSIS; untuk "true_false" tulis "Benar" atau "Salah"; untuk "short_answer" tulis jawaban singkat.',
            '- "source_chunk": nomor [CHUNK n] yang menjadi sumber soal.',
            '',
            'Materi:',
            '',
            $context,
        ]);
    }
}
