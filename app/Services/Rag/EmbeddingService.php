<?php

namespace App\Services\Rag;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Memanggil Google Gemini Embeddings API.
 *
 * Konfigurasi dibaca dari config/gemini.php.
 * Dokumentasi: https://ai.google.dev/api/embeddings
 *
 * Model `gemini-embedding-001` default mengembalikan 3072 dimensi. Kita paksa
 * 768 dimensi via parameter `outputDimensionality` agar sinkron dengan kolom
 * `vector(768)` di tabel document_chunks.
 */
class EmbeddingService
{
    public const TASK_DOCUMENT = 'RETRIEVAL_DOCUMENT';

    public const TASK_QUERY = 'RETRIEVAL_QUERY';

    /** Status HTTP transien yang layak dicoba ulang (rate limit + server sibuk). */
    protected const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    /** Jumlah percobaan maksimal saat menghadapi error transien. */
    protected const MAX_ATTEMPTS = 3;

    /**
     * Dimensi vector yang diharapkan (dibaca dari config saat boot).
     */
    public int $embeddingDim;

    protected string $baseUrl;

    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $model = null,
        protected ?int $timeoutSeconds = null,
    ) {
        $this->apiKey = $this->apiKey ?: (string) config('gemini.api_key');
        $this->model = $this->model ?: (string) config('gemini.embedding_model', 'gemini-embedding-001');
        $this->timeoutSeconds = $this->timeoutSeconds ?: (int) config('gemini.timeout', 30);
        $this->embeddingDim = (int) config('gemini.embedding_dimensions', 768);
        $this->baseUrl = rtrim((string) config('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    /**
     * Hasilkan embedding untuk satu string teks.
     *
     * @param  string  $text    Teks yang akan di-embed.
     * @param  string  $taskType  TASK_DOCUMENT (default, indexing) atau TASK_QUERY
     *                           (saat menerjemahkan pertanyaan user di RAG).
     * @return array<int, float>
     */
    public function embed(string $text, string $taskType = self::TASK_DOCUMENT): array
    {
        $this->ensureConfigured();

        $url = sprintf('%s/models/%s:embedContent', $this->baseUrl, $this->model);

        $response = $this->callWithRetry($url, [
            'content' => [
                'parts' => [['text' => $text]],
            ],
            'taskType' => $taskType,
            'outputDimensionality' => $this->embeddingDim,
        ]);

        $values = data_get($response->json(), 'embedding.values');

        if (! is_array($values) || count($values) !== $this->embeddingDim) {
            throw new RuntimeException(sprintf(
                'Respons embedding tidak valid: dimensi yang diharapkan %d, diterima %s.',
                $this->embeddingDim,
                is_array($values) ? count($values) : gettype($values),
            ));
        }

        return array_map('floatval', $values);
    }

    /**
     * Panggil API dengan retry untuk error transien (rate limit 429 + server
     * sibuk 5xx, mis. 503 "UNAVAILABLE"). Backoff bertambah: 1 detik, lalu
     * 2 detik. Error permanen (mis. 401, 404) langsung dilempar tanpa retry.
     */
    protected function callWithRetry(string $url, array $payload): Response
    {
        $response = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->post($url, $payload);

            // Sukses atau error permanen → berhenti.
            if (! in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                break;
            }

            // Masih ada sisa percobaan → tunggu sebentar lalu ulangi.
            if ($attempt < self::MAX_ATTEMPTS) {
                sleep($attempt);
            }
        }

        if ($response->failed()) {
            if (in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                throw new RuntimeException(sprintf(
                    'Layanan embedding Gemini sedang sibuk atau tidak tersedia (HTTP %d). '.
                    'Coba lagi beberapa saat lagi.',
                    $response->status(),
                ));
            }

            throw new RuntimeException(sprintf(
                'Panggilan embedding gagal (HTTP %d): %s',
                $response->status(),
                $response->body(),
            ));
        }

        return $response;
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException(
                'GEMINI_API_KEY belum diisi di .env. Lihat config/gemini.php.'
            );
        }
    }
}
