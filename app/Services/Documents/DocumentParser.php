<?php

namespace App\Services\Documents;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class DocumentParser
{
    /**
     * Threshold minimal kata yang harus terbaca. PDF berbasis gambar (scan)
     * biasanya menghasilkan teks sangat sedikit; ditolak agar tidak memboroskan
     * kuota embedding di Fase 6.
     */
    protected const MIN_WORD_COUNT = 50;

    public function __construct(protected PdfParser $parser = new PdfParser()) {}

    /**
     * Parse PDF milik $document. Update kolom extracted_text, page_count,
     * word_count, status, error_message, processed_at sesuai hasil.
     *
     * Method ini selalu mengembalikan instance Document yang sudah di-refresh,
     * jadi caller bisa langsung cek status.
     */
    public function parse(Document $document): Document
    {
        $document->update([
            'status' => Document::STATUS_PROCESSING,
        ]);

        try {
            $absolutePath = Storage::disk('local')->path($document->file_path);

            if (! is_file($absolutePath)) {
                throw new \RuntimeException('File PDF tidak ditemukan di storage.');
            }

            $pdf = $this->parser->parseFile($absolutePath);
            $pages = $pdf->getPages();
            $pageCount = count($pages);

            $text = trim($pdf->getText());
            // Normalisasi whitespace ganda menjadi satu spasi (lebih bersih untuk chunking).
            $text = (string) preg_replace('/\s+/u', ' ', $text);

            $wordCount = $text === '' ? 0 : count(preg_split('/\s+/u', $text));

            // Validasi: dokumen tanpa teks (kemungkinan PDF gambar) ditolak.
            if ($wordCount < self::MIN_WORD_COUNT) {
                throw new \RuntimeException(
                    sprintf(
                        'Teks pada PDF terlalu sedikit (%d kata). Pastikan PDF berisi teks, bukan hasil pemindaian gambar.',
                        $wordCount,
                    )
                );
            }

            // Validasi batas halaman untuk demo (sesuai CLAUDE.md).
            $statusFinal = $pageCount > Document::MAX_PAGES
                ? Document::STATUS_FAILED
                : Document::STATUS_READY;

            $errorMessage = $pageCount > Document::MAX_PAGES
                ? sprintf('Jumlah halaman (%d) melebihi batas demo %d halaman.', $pageCount, Document::MAX_PAGES)
                : null;

            $document->update([
                'extracted_text' => $text,
                'page_count' => $pageCount,
                'word_count' => $wordCount,
                'status' => $statusFinal,
                'error_message' => $errorMessage,
                'processed_at' => now(),
            ]);

            return $document->fresh();
        } catch (Throwable $e) {
            $document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            return $document->fresh();
        }
    }
}
