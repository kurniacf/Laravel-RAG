<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Google Gemini
|--------------------------------------------------------------------------
|
| API key didapat gratis dari https://aistudio.google.com. Isi nilainya
| di file `.env` (kunci: GEMINI_API_KEY). Jangan commit key ke repo.
|
| - `model`              : model chat/generasi (untuk Q&A, ringkasan, kuis).
| - `embedding_model`    : model embedding teks (default 768 dimensi).
| - `embedding_dimensions`: harus sama dengan tipe kolom `vector(N)` di
|   migration `document_chunks`.
|
*/

return [

    /**
     * API key untuk autentikasi ke Google Generative Language API.
     * Header request: `x-goog-api-key: <key>`.
     */
    'api_key' => env('GEMINI_API_KEY'),

    /**
     * Model generative (chat / ringkasan / kuis / flashcard).
     * Pilihan ringan-cepat untuk demo: `gemini-2.5-flash-lite`.
     */
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),

    /**
     * Model embedding untuk vector store.
     *
     * Per saat schema disusun, model embedding yang tersedia di Gemini API
     * adalah `gemini-embedding-001` (default 3072 dim, support `outputDimensionality`).
     * Model lama seperti `text-embedding-001` / `text-embedding-004` sudah
     * dihapus dari endpoint v1beta.
     */
    'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),

    /**
     * Dimensi vector yang dihasilkan. HARUS sinkron dengan kolom vector(N)
     * di tabel document_chunks.
     */
    'embedding_dimensions' => (int) env('GEMINI_EMBEDDING_DIMENSIONS', 768),

    /**
     * Base URL Generative Language API (v1beta saat ini stabil untuk
     * embed-content & generate-content endpoint).
     */
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /**
     * Timeout (detik) untuk panggilan HTTP ke Gemini.
     */
    'timeout' => (int) env('GEMINI_TIMEOUT', 30),

];
