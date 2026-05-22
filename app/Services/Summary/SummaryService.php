<?php

namespace App\Services\Summary;

use App\Models\AiJob;
use App\Models\Document;
use App\Models\Summary;
use App\Services\Rag\GeminiChatService;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Membuat ringkasan otomatis tiga tingkat untuk satu dokumen:
 *  - executive   : ringkasan eksekutif satu paragraf.
 *  - per_chapter : ringkasan terstruktur per bagian.
 *  - key_points  : daftar poin kunci.
 *
 * Sumber teks: kolom `documents.extracted_text` (teks penuh hasil parse PDF).
 * Sengaja TIDAK menggabungkan ulang document_chunks: chunk dibuat dengan
 * overlap 200 karakter, jadi menyambungnya akan menduplikasi teks di batas
 * chunk. `extracted_text` adalah teks kanonik yang utuh dan bersih. Ringkasan
 * juga tidak butuh embedding sama sekali — nol panggilan kuota embedding.
 *
 * Hasil di-cache di tabel `summaries`; regenerate me-replace baris lama
 * lewat updateOrCreate. Setiap pemanggilan dicatat ke satu baris ai_jobs.
 */
class SummaryService
{
    /**
     * Batas karakter teks yang dikirim ke Gemini. PDF sudah dibatasi 30
     * halaman, tapi ini jaring pengaman tambahan agar token tetap terkendali.
     */
    public const MAX_INPUT_CHARS = 60000;

    public function __construct(protected GeminiChatService $chat) {}

    /**
     * Generate ketiga tipe ringkasan, simpan ke DB, catat satu AiJob.
     *
     * @return Collection<int, Summary>
     *
     * @throws RuntimeException bila dokumen tidak siap atau panggilan AI gagal.
     */
    public function generateAll(Document $document): Collection
    {
        if (! $document->isReady()) {
            throw new RuntimeException('Hanya dokumen berstatus "Siap" yang dapat diringkas.');
        }

        $text = trim((string) $document->extracted_text);

        if ($text === '') {
            throw new RuntimeException('Dokumen tidak memiliki teks hasil ekstraksi untuk diringkas.');
        }

        $text = mb_substr($text, 0, self::MAX_INPUT_CHARS);

        $job = AiJob::create([
            'document_id' => $document->id,
            'job_type' => AiJob::TYPE_SUMMARIZE,
            'status' => AiJob::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $start = microtime(true);

        try {
            $summaries = collect();
            $totalTokens = 0;

            foreach (Summary::TYPES as $type) {
                $summary = $this->generateOne($document, $type, $text);
                $totalTokens += (int) $summary->tokens_used;
                $summaries->push($summary);
            }

            $job->update([
                'status' => AiJob::STATUS_COMPLETED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'tokens_used' => $totalTokens,
            ]);

            return $summaries;
        } catch (Throwable $e) {
            $job->update([
                'status' => AiJob::STATUS_FAILED,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate satu tipe ringkasan via Gemini lalu simpan (replace bila ada).
     */
    protected function generateOne(Document $document, string $type, string $text): Summary
    {
        $result = $this->chat->generate(
            $this->systemInstruction(),
            $this->userMessage($document->title, $type, $text),
            [
                'temperature' => 0.3,
                'max_output_tokens' => $this->maxTokensFor($type),
            ],
        );

        $content = trim($result['answer']);

        return Summary::updateOrCreate(
            [
                'document_id' => $document->id,
                'type' => $type,
            ],
            [
                'content' => $content,
                'word_count' => $this->countWords($content),
                'tokens_used' => $result['tokens_used'],
                'model_used' => (string) config('gemini.model'),
            ],
        );
    }

    /**
     * System instruction bersama untuk ketiga tipe — fokus anti-halusinasi.
     */
    protected function systemInstruction(): string
    {
        return <<<'TXT'
        Kamu adalah asisten akademik PintarBelajar AI yang membuat ringkasan
        materi belajar untuk pelajar Indonesia.

        ATURAN WAJIB:
        1. Ringkas HANYA berdasarkan teks materi yang diberikan di antara
           penanda ===== MATERI ===== dan ===== AKHIR MATERI =====.
        2. DILARANG menambah fakta, contoh, angka, atau informasi apa pun yang
           tidak ada di materi. Jangan mengarang.
        3. Bila materi tidak membahas suatu hal, jangan menyinggungnya.
        4. Gunakan Bahasa Indonesia baku yang jelas dan mudah dipahami pelajar.
        5. Langsung ke isi. Jangan menulis kalimat pembuka basa-basi seperti
           "Berikut ringkasannya" atau "Tentu, ...".
        6. Rumuskan ulang dengan bahasamu sendiri secara ringkas; jangan
           menyalin kalimat materi mentah-mentah.
        TXT;
    }

    /**
     * Susun instruksi spesifik per tipe lalu sisipkan teks materi.
     */
    protected function userMessage(string $documentTitle, string $type, string $text): string
    {
        $instruction = match ($type) {
            Summary::TYPE_EXECUTIVE => <<<TXT
            Buat RINGKASAN EKSEKUTIF dari materi berjudul "{$documentTitle}".
            Tulis SATU paragraf padat (4-6 kalimat) yang memberi gambaran
            menyeluruh: topik utama, cakupan pembahasan, dan inti materi.
            Jangan memakai bullet, penomoran, atau subjudul.
            TXT,
            Summary::TYPE_PER_CHAPTER => <<<TXT
            Buat RINGKASAN PER BAGIAN dari materi berjudul "{$documentTitle}".
            Bagi materi menjadi 3-7 bagian/subtopik logis sesuai urutan
            pembahasannya. Tulis SETIAP bagian dengan format persis:
            ### Judul Bagian
            Ringkasan 1-3 kalimat untuk bagian tersebut.

            Pisahkan antar bagian dengan satu baris kosong.
            TXT,
            Summary::TYPE_KEY_POINTS => <<<TXT
            Buat DAFTAR POIN KUNCI dari materi berjudul "{$documentTitle}" —
            hal-hal terpenting yang wajib diingat pelajar. Tulis 5-8 poin.
            Setiap poin pada barisnya sendiri dan diawali tanda "- " (tanda
            hubung diikuti spasi). Tiap poin maksimal 2 kalimat. Jangan beri
            penomoran dan jangan subjudul.
            TXT,
            default => throw new RuntimeException("Tipe ringkasan tidak dikenal: {$type}"),
        };

        return $instruction
            ."\n\n===== MATERI =====\n"
            .$text
            ."\n===== AKHIR MATERI =====";
    }

    /**
     * Batas token output per tipe ringkasan (per_chapter butuh ruang lebih).
     */
    protected function maxTokensFor(string $type): int
    {
        return match ($type) {
            Summary::TYPE_EXECUTIVE => 600,
            Summary::TYPE_PER_CHAPTER => 1600,
            Summary::TYPE_KEY_POINTS => 900,
            default => 1024,
        };
    }

    /**
     * Hitung jumlah kata. Berbasis spasi agar akurat untuk Bahasa Indonesia
     * (str_word_count bawaan PHP kurang andal untuk karakter non-ASCII).
     */
    protected function countWords(string $content): int
    {
        return count(preg_split('/\s+/u', trim($content), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
