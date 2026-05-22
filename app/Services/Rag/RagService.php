<?php

namespace App\Services\Rag;

use App\Models\ChatMessage;
use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline RAG: query rewriting heuristik → embed → retrieve top-K dengan
 * threshold → susun prompt → panggil Gemini Chat → kembalikan jawaban,
 * cited_chunk_ids, dan label sumber (dari dokumen / pengetahuan umum / tolak).
 *
 * Catatan desain:
 * - Tidak panggil Gemini untuk rewrite (hemat kuota). Heuristik regex sudah
 *   menutup kasus paling umum (pertanyaan ambigu, follow-up dengan pronoun).
 * - Threshold cosine 0.85 cukup permisif: chunk yang masih relevan tetap
 *   masuk, chunk noise di-discard. Minimal 3 chunks dipertahankan supaya
 *   model selalu punya konteks (kecuali dokumen memang kosong).
 */
class RagService
{
    /** Jumlah chunk teratas yang diambil per query. */
    public const TOP_K = 8;

    /**
     * Maksimal cosine distance pgvector (`<=>`) agar chunk dianggap relevan.
     * Cosine distance 0=identik, 1=ortogonal, 2=berlawanan.
     * Empirik untuk gemini-embedding-001 + materi belajar: 0.85 cocok.
     */
    public const DISTANCE_THRESHOLD = 0.85;

    /** Minimal chunks yang dipertahankan walau di atas threshold. */
    public const MIN_CHUNKS = 3;

    /** Jumlah pesan riwayat yang dipakai untuk konteks query rewrite. */
    public const HISTORY_CONTEXT_COUNT = 2;

    /** Batas panjang karakter pertanyaan user (defense-in-depth). */
    public const MAX_QUESTION_LENGTH = 2000;

    /**
     * Pola injection yang dicatat di log (audit). Bukan untuk diblokir di
     * level kode — model di-prompt untuk menolak. Logging berguna untuk:
     *  - Telemetri usaha manipulasi
     *  - Investigasi bila terjadi kebocoran
     */
    protected const INJECTION_PATTERNS = [
        '/abaikan\s+(instruksi|aturan|perintah)/iu',
        '/ignore\s+(previous|prior|all)\s+(instructions?|rules?)/iu',
        '/tampilkan\s+(system\s*)?prompt/iu',
        '/bocorkan\s+(system\s*)?prompt/iu',
        '/show\s+(me\s+)?(your\s+)?(system\s*)?prompt/iu',
        '/reveal\s+(your\s+)?(system\s*)?prompt/iu',
        '/jailbreak/iu',
        '/pura.?pura\s+(jadi|sebagai|menjadi)/iu',
        '/pretend\s+to\s+be/iu',
        '/act\s+as\s+(if|a|an)/iu',
        '/berperan\s+sebagai/iu',
        '/\bDAN\b/u', // "Do Anything Now" jailbreak meme
        '/developer\s+mode/iu',
    ];

    public function __construct(
        protected EmbeddingService $embedder,
        protected GeminiChatService $chat,
    ) {}

    /**
     * Tanyakan pertanyaan terhadap satu dokumen ter-vektorisasi.
     *
     * @param  Collection<int, ChatMessage>|null  $history  Riwayat pesan sebelumnya untuk follow-up rewrite.
     * @return array{answer:string, cited_chunk_ids:array<int,int>, tokens_used:?int, source:string}
     */
    public function ask(Document $document, string $question, ?Collection $history = null): array
    {
        // 0) Sanitize input user (strip delimiter sistem, normalisasi).
        $question = $this->sanitizeQuestion($question);
        //akuada lahseor sisw
        // 0b) Catat upaya injection (tidak memblokir; model akan menolak).
        $this->logIfInjection($question, $document);

        // 1) Query rewriting heuristik untuk kasus ambigu / follow-up.
        $effectiveQuery = $this->rewriteQuery($question, $document, $history);

        // 2) Embed (TASK_QUERY agar embedding pas untuk pencarian).
        $queryVector = $this->embedder->embed($effectiveQuery, EmbeddingService::TASK_QUERY);
        $queryLiteral = DocumentIndexer::vectorLiteral($queryVector);

        // 3) Retrieve top-K, pertahankan minimal MIN_CHUNKS, filter sisanya
        //    dengan threshold relevansi.
        $allChunks = DB::select(
            'SELECT id, chunk_index, content, page_number, embedding <=> ?::vector AS distance
             FROM document_chunks
             WHERE document_id = ? AND embedding IS NOT NULL
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$queryLiteral, $document->id, $queryLiteral, self::TOP_K],
        );

        $relevantChunks = $this->filterByThreshold($allChunks);

        if (empty($relevantChunks)) {
            return [
                'answer' => 'Dokumen ini belum diproses ke vector store. Jalankan "Proses ke Vector" di halaman dokumen terlebih dahulu.',
                'cited_chunk_ids' => [],
                'tokens_used' => null,
                'source' => ChatMessage::SOURCE_REFUSED,
            ];
        }

        // 4) Susun blok konteks dengan delimiter eksplisit. Content chunk juga
        //    di-sanitize: kalau ada teks dokumen yang sengaja di-craft attacker
        //    untuk menyusupkan delimiter, dibersihkan supaya tidak bisa "keluar"
        //    dari blok [DOKUMEN].
        $contextBlocks = [];
        $citedIds = [];
        foreach ($relevantChunks as $i => $row) {
            $page = $row->page_number !== null ? "Halaman {$row->page_number}" : 'Halaman tidak diketahui';
            $contextBlocks[] = sprintf(
                "[DOKUMEN — Chunk %d, %s]\n%s\n[/DOKUMEN]",
                $i + 1,
                $page,
                $this->sanitizeChunkContent($row->content),
            );
            $citedIds[] = (int) $row->id;
        }
        $context = implode("\n\n", $contextBlocks);

        // 5) Susun system instruction (Fase 2 — di-tarik dari PromptBuilder).
        $systemInstruction = PromptBuilder::build($document->title);

        // 6) User message — pertanyaan asli (bukan rewritten) di-delimit eksplisit.
        $userMessage = $this->composeUserMessage($context, $question);

        // 7) Panggil Gemini.
        $result = $this->chat->generate($systemInstruction, $userMessage, [
            'temperature' => 0.3,
            'max_output_tokens' => 1024,
        ]);

        // 8) Tentukan source label berdasarkan teks jawaban.
        $source = $this->detectSource($result['answer']);

        return [
            'answer' => $this->stripChunkMarkers($result['answer']),
            // Citation hanya bermakna untuk jawaban yang BENAR-BENAR dari dokumen.
            // Untuk 'general' (di luar dokumen) dan 'refused' (penolakan), kosongkan
            // supaya UI tidak menyesatkan dengan badge sumber yang tidak relevan.
            'cited_chunk_ids' => $source === ChatMessage::SOURCE_DOCUMENT ? $citedIds : [],
            'tokens_used' => $result['tokens_used'],
            'source' => $source,
        ];
    }

    /**
     * Heuristik ringan untuk memperluas query agar embedding lebih bermakna.
     * Trigger: pertanyaan sangat pendek, diawali kata tanya generik, atau
     * mengandung pronoun referensial ("itu/ini/tersebut").
     */
    protected function rewriteQuery(string $question, Document $document, ?Collection $history): string
    {
        $q = trim($question);
        $wordCount = count(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY));

        $isAmbiguousStart = (bool) preg_match('/^(apa|kenapa|gimana|bagaimana|kok|lalu|terus|jelaskan|sebutkan)\b/iu', $q);
        $hasPronoun = (bool) preg_match('/\b(itu|ini|tersebut|tadi|nya|begitu)\b/iu', $q);

        $needsRewrite = $wordCount < 4 || ($isAmbiguousStart && $wordCount < 7) || $hasPronoun;

        if (! $needsRewrite) {
            return $q;
        }

        $prefix = sprintf('Dalam konteks dokumen "%s"', $document->title);

        // Tambahkan ringkasan 2 pesan terakhir untuk follow-up.
        if ($history !== null && $history->isNotEmpty()) {
            $recent = $history
                ->take(-self::HISTORY_CONTEXT_COUNT)
                ->pluck('content')
                ->map(fn ($c) => mb_substr(trim($c), 0, 120))
                ->implode(' | ');
            if ($recent !== '') {
                $prefix .= sprintf(' (sebelumnya: %s)', $recent);
            }
        }

        return "{$prefix}, {$q}";
    }

    /**
     * Pertahankan minimal MIN_CHUNKS, lalu buang sisanya bila distance > THRESHOLD.
     *
     * @param  array<int, object>  $chunks
     * @return array<int, object>
     */
    protected function filterByThreshold(array $chunks): array
    {
        if (empty($chunks)) {
            return [];
        }

        // Pertahankan top MIN_CHUNKS apa adanya.
        $kept = array_slice($chunks, 0, self::MIN_CHUNKS);

        // Sisanya, terima hanya kalau distance di bawah threshold.
        foreach (array_slice($chunks, self::MIN_CHUNKS) as $c) {
            if ((float) $c->distance <= self::DISTANCE_THRESHOLD) {
                $kept[] = $c;
            }
        }

        return $kept;
    }

    /**
     * Compose user message: context block + pertanyaan user dalam delimiter
     * eksplisit. Delimiter `<<<USER_QUESTION>>>` membantu guardrail menegaskan
     * bahwa segala isi di dalamnya adalah DATA pertanyaan, bukan instruksi.
     */
    protected function composeUserMessage(string $context, string $question): string
    {
        return implode("\n\n", [
            'Berikut beberapa kutipan dokumen yang dipilih sebagai konteks:',
            $context,
            '<<<USER_QUESTION>>>',
            trim($question),
            '<<<END_USER_QUESTION>>>',
        ]);
    }

    /**
     * Klasifikasikan jawaban berdasarkan signal teks: apakah model menjawab
     * dari dokumen, dari pengetahuan umum (label "Di luar isi dokumen"), atau
     * menolak.
     */
    protected function detectSource(string $answer): string
    {
        $lower = mb_strtolower($answer);

        if (str_contains($lower, 'di luar isi dokumen')) {
            return ChatMessage::SOURCE_GENERAL;
        }

        // Frasa khas penolakan / arahkan kembali ke materi.
        $refusePatterns = [
            'tidak dapat membantu',
            'tidak bisa membantu',
            'mari kembali ke materi',
            'silakan tanyakan tentang materi',
            'di luar topik belajar',
            'tidak ditemukan dalam dokumen',
        ];
        foreach ($refusePatterns as $p) {
            if (str_contains($lower, $p)) {
                return ChatMessage::SOURCE_REFUSED;
            }
        }

        return ChatMessage::SOURCE_DOCUMENT;
    }

    /**
     * Bersihkan jawaban dari penanda chunk yang mungkin bocor (defense ekstra
     * meskipun system prompt sudah melarang).
     */
    protected function stripChunkMarkers(string $answer): string
    {
        $clean = preg_replace(
            ['/\[\s*CHUNK\s*\d+\s*\]/iu', '/\[\s*\/?DOKUMEN[^\]]*\]/iu'],
            '',
            $answer,
        );

        // Rapikan whitespace berlebih akibat penghapusan.
        return trim((string) preg_replace('/[ \t]+/u', ' ', $clean));
    }

    /**
     * Sanitasi pertanyaan user:
     * - hapus karakter kontrol & normalisasi whitespace
     * - hapus token delimiter sistem agar user tidak bisa keluar dari blok
     *   pertanyaannya sendiri (mis. menutup <<<USER_QUESTION>>> lalu menyuntik
     *   instruksi tambahan)
     * - potong panjang maksimum (defense-in-depth, Livewire sudah validasi)
     */
    public static function sanitizeQuestion(string $question): string
    {
        // Hapus karakter kontrol (kecuali newline biasa) lalu normalisasi.
        $q = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $question) ?? '';

        // Hapus token delimiter sistem (case-insensitive).
        $q = preg_replace(
            [
                '/<<<\s*USER_QUESTION\s*>>>/iu',
                '/<<<\s*END_USER_QUESTION\s*>>>/iu',
                '/\[\s*\/?DOKUMEN[^\]]*\]/iu',
                '/\[\s*CHUNK\s*\d+[^\]]*\]/iu',
                '/\[\s*(PERAN|ATURAN[^\]]*|GAYA[^\]]*|KEBIJAKAN[^\]]*|CARA\s+BERPIKIR[^\]]*|CONTOH[^\]]*)\s*\]/iu',
            ],
            ' ',
            $q,
        );

        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');

        if (mb_strlen($q) > self::MAX_QUESTION_LENGTH) {
            $q = mb_substr($q, 0, self::MAX_QUESTION_LENGTH);
        }

        return $q;
    }

    /**
     * Sanitasi isi chunk dokumen sebelum dibungkus ke blok [DOKUMEN].
     * Mencegah attacker yang sengaja menaruh string delimiter di dokumen
     * untuk "menutup" blok dan menyuntik instruksi setelahnya.
     */
    protected function sanitizeChunkContent(string $content): string
    {
        $clean = preg_replace(
            [
                '/<<<\s*USER_QUESTION\s*>>>/iu',
                '/<<<\s*END_USER_QUESTION\s*>>>/iu',
                '/\[\s*\/?DOKUMEN[^\]]*\]/iu',
            ],
            ' ',
            $content,
        );

        return trim($clean ?? '');
    }

    /**
     * Catat upaya prompt injection. Tidak memblokir — model sudah diinstruksikan
     * menolak. Log berguna untuk audit telemetri keamanan.
     */
    protected function logIfInjection(string $question, Document $document): void
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $question)) {
                Log::warning('rag.injection_attempt', [
                    'pattern' => $pattern,
                    'question_preview' => mb_substr($question, 0, 200),
                    'document_id' => $document->id,
                    'user_id' => Auth::id(),
                ]);

                // Cukup satu pattern di-log per request; tidak perlu spam log.
                return;
            }
        }
    }
}
