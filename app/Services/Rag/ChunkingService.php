<?php

namespace App\Services\Rag;

/**
 * Memecah teks dokumen menjadi chunk dengan ukuran target ~512 token dan
 * overlap ~50 token. Pendekatan: sliding window karakter (1 token ≈ 4 char
 * untuk teks campuran Indonesia/Inggris).
 */
class ChunkingService
{
    public function __construct(
        protected int $chunkChars = 2000,    // ~512 token
        protected int $overlapChars = 200,   // ~50 token
    ) {}

    /**
     * Pecah teks menjadi array of strings.
     *
     * @return array<int, string>
     */
    public function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        // Bila teks lebih pendek dari chunkChars, kembalikan apa adanya.
        if (mb_strlen($text) <= $this->chunkChars) {
            return [$text];
        }

        $chunks = [];
        $position = 0;
        $length = mb_strlen($text);

        while ($position < $length) {
            $chunk = mb_substr($text, $position, $this->chunkChars);

            // Coba potong di batas kata terdekat agar tidak memotong tengah kata.
            if (mb_strlen($chunk) === $this->chunkChars && $position + $this->chunkChars < $length) {
                $lastSpace = mb_strrpos($chunk, ' ');
                if ($lastSpace !== false && $lastSpace > $this->chunkChars * 0.7) {
                    $chunk = mb_substr($chunk, 0, $lastSpace);
                }
            }

            $chunk = trim($chunk);
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            // Maju sebesar chunk length - overlap.
            $advance = mb_strlen($chunk) - $this->overlapChars;
            if ($advance < 1) {
                $advance = mb_strlen($chunk);
            }

            $position += $advance;
        }

        return $chunks;
    }

    /**
     * Estimasi jumlah token untuk satu string (rough: 1 token ≈ 4 char).
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
