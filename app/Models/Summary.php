<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'type',
    'content',
    'word_count',
    'tokens_used',
    'model_used',
])]
class Summary extends Model
{
    /** Ringkasan eksekutif: satu paragraf gambaran menyeluruh. */
    public const TYPE_EXECUTIVE = 'executive';

    /** Ringkasan per bagian/bab: terstruktur dengan subjudul. */
    public const TYPE_PER_CHAPTER = 'per_chapter';

    /** Poin kunci: daftar bullet hal terpenting. */
    public const TYPE_KEY_POINTS = 'key_points';

    /** Urutan tampil sekaligus daftar tipe yang valid. */
    public const TYPES = [
        self::TYPE_EXECUTIVE,
        self::TYPE_PER_CHAPTER,
        self::TYPE_KEY_POINTS,
    ];

    protected function casts(): array
    {
        return [
            'word_count' => 'integer',
            'tokens_used' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Label tipe untuk ditampilkan di UI. */
    public function typeLabel(): string
    {
        return self::labelFor($this->type);
    }

    public static function labelFor(string $type): string
    {
        return match ($type) {
            self::TYPE_EXECUTIVE => 'Ringkasan Eksekutif',
            self::TYPE_PER_CHAPTER => 'Ringkasan Per Bagian',
            self::TYPE_KEY_POINTS => 'Poin Kunci',
            default => $type,
        };
    }

    /**
     * Pecah konten "ringkasan per bagian" menjadi daftar bagian terstruktur.
     * Heading (markdown `#`..`####` atau baris yang seluruhnya **tebal**)
     * memulai bagian baru. Teks sebelum heading pertama menjadi bagian tanpa
     * judul. Bila tak ada heading sama sekali, seluruh konten dikembalikan
     * sebagai satu bagian tanpa judul (fallback agar tab tidak terlihat kosong).
     *
     * @return array<int, array{title: string, body: string}>
     */
    public function sections(): array
    {
        $lines = preg_split('/\r?\n/', (string) $this->content) ?: [];

        $sections = [];
        $current = null;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                continue;
            }

            $heading = $this->detectHeading($line);

            if ($heading !== null) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = ['title' => $heading, 'body' => ''];

                continue;
            }

            if ($current === null) {
                $current = ['title' => '', 'body' => ''];
            }

            $current['body'] = $current['body'] === ''
                ? $line
                : $current['body']."\n".$line;
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        return $sections;
    }

    /**
     * Pecah konten "poin kunci" menjadi daftar poin, membuang penanda bullet
     * atau nomor di awal tiap baris.
     *
     * @return array<int, string>
     */
    public function points(): array
    {
        $lines = preg_split('/\r?\n/', (string) $this->content) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '')
            ->map(fn ($line) => trim((string) preg_replace('/^\s*(?:[-*•‣◦]|\d+[.)])\s+/u', '', $line)))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();
    }

    /**
     * Deteksi apakah sebuah baris adalah heading bagian. Mengembalikan judul
     * tanpa penanda, atau null bila baris tersebut bukan heading.
     */
    protected function detectHeading(string $line): ?string
    {
        // Markdown ATX heading: "# ", "## ", "### ", "#### ".
        if (preg_match('/^#{1,4}\s+(.+)$/u', $line, $m)) {
            return trim($m[1]);
        }

        // Baris yang seluruhnya tebal: "**Judul**" atau "**Judul:**".
        if (preg_match('/^\*\*([^*]+?)\*\*:?$/u', $line, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
