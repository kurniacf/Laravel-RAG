<?php

namespace App\Services\Rag;

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

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeoutSeconds)
            ->post($url, $payload);

        if ($response->status() === 429) {
            sleep(1);
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,=
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->post($url, $payload);
        }

        if ($response->failed()) {
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
}
