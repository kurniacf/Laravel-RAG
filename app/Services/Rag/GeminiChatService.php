<?php

namespace App\Services\Rag;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Wrapper untuk Google Gemini generateContent API.
 *
 * Endpoint: POST /v1beta/models/{model}:generateContent
 * Body shape:
 *  {
 *    "contents": [{ "role": "user", "parts": [{"text": "..."}] }],
 *    "systemInstruction": { "parts": [{"text": "..."}] },
 *    "generationConfig": { "temperature": 0.2, "maxOutputTokens": 1024 }
 *  }
 */
class GeminiChatService
{
    /**
     * Status HTTP transien yang layak dicoba ulang: rate limit (429) dan
     * server sibuk / tidak tersedia (500, 502, 503, 504). Gemini sering balas
     * 503 "UNAVAILABLE" saat beban tinggi — biasanya pulih dalam hitungan detik.
     */
    protected const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    /** Jumlah percobaan maksimal saat menghadapi error transien. */
    protected const MAX_ATTEMPTS = 3;

    protected string $apiKey;

    protected string $model;

    protected string $baseUrl;

    protected int $timeoutSeconds;

    public function __construct()
    {
        $this->apiKey = (string) config('gemini.api_key');
        $this->model = (string) config('gemini.model', 'gemini-2.5-flash-lite');
        $this->baseUrl = rtrim((string) config('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->timeoutSeconds = (int) config('gemini.timeout', 60);
    }

    /**
     * Hasilkan jawaban dari user message dengan system instruction sebagai panduan.
     *
     * @return array{answer:string, tokens_used:?int}
     */
    public function generate(string $systemInstruction, string $userMessage, array $options = []): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException(
                'GEMINI_API_KEY belum diisi di .env. Lihat config/gemini.php.'
            );
        }

        $url = sprintf('%s/models/%s:generateContent', $this->baseUrl, $this->model);

        $temperature = $options['temperature'] ?? 0.2;
        $maxTokens = $options['max_output_tokens'] ?? 1024;

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $userMessage]],
            ]],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        // Minta output dengan MIME type tertentu (mis. 'application/json' agar
        // Gemini mengembalikan JSON murni tanpa pembungkus markdown).
        if (! empty($options['response_mime_type'])) {
            $payload['generationConfig']['responseMimeType'] = (string) $options['response_mime_type'];
        }

        $response = $this->sendWithRetry($url, $payload);

        if ($response->failed()) {
            // Error transien (rate limit / server sibuk) sudah dicoba ulang
            // beberapa kali — beri pesan ramah, bukan dump JSON mentah.
            if (in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                throw new RuntimeException(sprintf(
                    'Layanan Gemini sedang sibuk atau tidak tersedia (HTTP %d). '.
                    'Coba lagi beberapa saat lagi.',
                    $response->status(),
                ));
            }

            throw new RuntimeException(sprintf(
                'Panggilan Gemini Chat gagal (HTTP %d): %s',
                $response->status(),
                $response->body(),
            ));
        }

        $json = $response->json();
        $answer = trim((string) data_get($json, 'candidates.0.content.parts.0.text', ''));

        if ($answer === '') {
            // Possible safety-blocked atau output kosong.
            $finishReason = (string) data_get($json, 'candidates.0.finishReason', 'UNKNOWN');
            throw new RuntimeException("Gemini mengembalikan jawaban kosong (finishReason: {$finishReason}).");
        }

        return [
            'answer' => $answer,
            'tokens_used' => (int) (data_get($json, 'usageMetadata.totalTokenCount') ?? 0) ?: null,
        ];
    }

    /**
     * Kirim request ke Gemini dengan retry untuk error transien (429 + 5xx).
     * Backoff bertambah: jeda 1 detik, lalu 2 detik. Error permanen (mis. 400,
     * 401, 404) langsung dikembalikan tanpa dicoba ulang.
     */
    protected function sendWithRetry(string $url, array $payload): Response
    {
        $response = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->post($url, $payload);

            // Sukses atau error permanen → tidak perlu dicoba ulang.
            if (! in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                return $response;
            }

            // Masih ada sisa percobaan → tunggu sebentar lalu ulangi.
            if ($attempt < self::MAX_ATTEMPTS) {
                sleep($attempt);
            }
        }

        return $response;
    }
}
