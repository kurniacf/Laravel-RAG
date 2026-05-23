<?php

namespace App\Services\Flashcard;

use App\Models\AiJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Flashcard;
use App\Services\Rag\GeminiChatService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Menghasilkan flashcard dari sebuah dokumen.
 *
 * Alur meniru QuizGeneratorService: sampling chunk → prompt Gemini untuk JSON
 * terstruktur → parse aman + retry → validasi tiap kartu → simpan ke tabel
 * flashcards. Dicatat ke ai_jobs (job_type flashcard_gen).
 *
 * Kartu dibuat dari `document_chunks` sehingga tiap kartu bisa ditelusuri ke
 * chunk asalnya lewat `source_chunk_id`. Generate bersifat APPEND — kartu baru
 * ditambahkan, tidak menghapus yang sudah ada.
 */
class FlashcardGeneratorService
{
    /** Maksimal percobaan ulang bila JSON dari Gemini gagal di-parse. */
    public const MAX_RETRIES = 2;

    public const MIN_CARDS = 5;

    public const MAX_CARDS = 20;

    /** Batas jumlah chunk yang dikirim sebagai sumber (kendali ukuran prompt). */
    public const MAX_SOURCE_CHUNKS = 12;

    public function __construct(protected GeminiChatService $chat) {}

    /**
     * Buat $count flashcard dari dokumen, di-APPEND ke kartu yang sudah ada.
     *
     * @return Collection<int, Flashcard>
     *
     * @throws RuntimeException bila dokumen tidak punya chunk atau JSON gagal.
     */
    public function generate(Document $document, int $count): Collection
    {
        $count = max(self::MIN_CARDS, min(self::MAX_CARDS, $count));

        $chunks = $this->sampleChunks($document);

        if ($chunks->isEmpty()) {
            throw new RuntimeException(
                'Dokumen belum punya chunk. Jalankan "Proses ke Vector" sebelum membuat flashcard.'
            );
        }

        $job = AiJob::create([
            'document_id' => $document->id,
            'job_type' => AiJob::TYPE_FLASHCARD_GEN,
            'status' => AiJob::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $start = microtime(true);
        $totalTokens = 0;

        try {
            $cards = [];
            $attempt = 0;

            do {
                $attempt++;

                $result = $this->chat->generate(
                    $this->systemInstruction(),
                    $this->userMessage($count, $chunks),
                    [
                        'temperature' => 0.4,
                        'max_output_tokens' => 4096,
                        'response_mime_type' => 'application/json',
                    ],
                );

                $totalTokens += (int) $result['tokens_used'];
                $cards = $this->parseAndValidate($result['answer'], $chunks);
            } while (empty($cards) && $attempt <= self::MAX_RETRIES);

            if (empty($cards)) {
                throw new RuntimeException(
                    "Gagal mendapatkan flashcard yang valid dari Gemini setelah {$attempt} percobaan."
                );
            }

            $saved = $this->persist($document, array_slice($cards, 0, $count));

            $job->update([
                'status' => AiJob::STATUS_COMPLETED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'tokens_used' => $totalTokens,
            ]);

            return $saved;
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
     * Ambil chunk sumber kartu; bila terlalu banyak, ambil sampel merata.
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
     * Parse JSON dari Gemini lalu validasi tiap kartu.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseAndValidate(string $raw, Collection $chunks): array
    {
        $json = $this->extractJson($raw);
        if ($json === null) {
            return [];
        }

        $rawCards = $json['flashcards'] ?? (array_is_list($json) ? $json : []);
        if (! is_array($rawCards)) {
            return [];
        }

        $valid = [];
        foreach ($rawCards as $card) {
            $normalized = $this->validateCard($card, $chunks);
            if ($normalized !== null) {
                $valid[] = $normalized;
            }
        }

        return $valid;
    }

    /** Ekstrak objek JSON, membuang pembungkus markdown fence bila ada. */
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
     * Validasi & normalisasi satu kartu. Return null bila tidak valid.
     *
     * @return array<string, mixed>|null
     */
    protected function validateCard(mixed $card, Collection $chunks): ?array
    {
        if (! is_array($card)) {
            return null;
        }

        $front = is_string($card['front_text'] ?? null) ? trim($card['front_text']) : '';
        $back = is_string($card['back_text'] ?? null) ? trim($card['back_text']) : '';

        if ($front === '' || $back === '') {
            return null;
        }

        return [
            'front_text' => $front,
            'back_text' => $back,
            'difficulty' => in_array($card['difficulty'] ?? null, Flashcard::DIFFICULTIES, true)
                ? $card['difficulty']
                : Flashcard::DIFFICULTY_MEDIUM,
            'source_chunk_id' => $this->resolveSourceChunkId($card['source_chunk'] ?? null, $chunks),
        ];
    }

    /** Petakan nomor source_chunk (1-based) ke id chunk yang sebenarnya. */
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
     * Simpan kartu — di-append; posisi melanjutkan dari kartu yang sudah ada.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return Collection<int, Flashcard>
     */
    protected function persist(Document $document, array $cards): Collection
    {
        return DB::transaction(function () use ($document, $cards) {
            $maxPosition = Flashcard::where('document_id', $document->id)->max('position');
            $nextPosition = $maxPosition === null ? 0 : (int) $maxPosition + 1;

            $saved = collect();
            foreach ($cards as $card) {
                $saved->push(Flashcard::create([
                    'document_id' => $document->id,
                    'user_id' => $document->user_id,
                    'source_chunk_id' => $card['source_chunk_id'],
                    'front_text' => $card['front_text'],
                    'back_text' => $card['back_text'],
                    'difficulty' => $card['difficulty'],
                    'position' => $nextPosition++,
                ]));
            }

            return $saved;
        });
    }

    protected function systemInstruction(): string
    {
        return <<<'TXT'
        Kamu adalah asisten akademik PintarBelajar AI yang membuat flashcard
        belajar dari materi untuk pelajar Indonesia.

        ATURAN WAJIB:
        1. Buat kartu HANYA berdasarkan isi materi pada blok [CHUNK n].
           DILARANG mengarang fakta, angka, atau contoh di luar materi.
        2. Sisi depan (front_text) berupa istilah atau pertanyaan SINGKAT;
           sisi belakang (back_text) berupa definisi atau jawaban yang jelas,
           akurat, dan ringkas.
        3. Tulis dalam Bahasa Indonesia baku.
        4. Keluarkan HANYA JSON valid sesuai skema — tanpa teks pembuka,
           tanpa komentar.
        TXT;
    }

    protected function userMessage(int $count, Collection $chunks): string
    {
        $context = $chunks->values()
            ->map(function ($c, $i) {
                $n = $i + 1;

                return "[CHUNK {$n}]\n".trim((string) $c->content)."\n[/CHUNK {$n}]";
            })
            ->implode("\n\n");

        $schema = '{"flashcards":[{'
            .'"front_text":"istilah atau pertanyaan singkat",'
            .'"back_text":"definisi atau jawaban yang jelas dan ringkas",'
            .'"difficulty":"easy | medium | hard",'
            .'"source_chunk":1'
            .'}]}';

        return implode("\n", [
            "Buat {$count} flashcard belajar dari materi di bawah.",
            '',
            'Setiap kartu menguji satu konsep penting. Sisi depan singkat'
            .' (istilah atau pertanyaan), sisi belakang ringkas namun lengkap.',
            '',
            'Keluarkan JSON dengan struktur PERSIS seperti contoh ini:',
            $schema,
            '',
            'Ketentuan field:',
            '- "difficulty": perkiraan tingkat kesulitan kartu (easy/medium/hard).',
            '- "source_chunk": nomor [CHUNK n] yang menjadi sumber kartu.',
            '',
            'Materi:',
            '',
            $context,
        ]);
    }
}
