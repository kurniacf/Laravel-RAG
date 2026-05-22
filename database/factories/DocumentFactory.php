<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->randomElement([
            'Pengantar Kalkulus Diferensial',
            'Mekanika Klasik Bab 1',
            'Struktur Sel dan Organel',
            'Algoritma dan Struktur Data',
            'Tata Bahasa Inggris Modern',
            'Pemrograman Berorientasi Objek',
            'Termodinamika Dasar',
            'Genetika Mendel',
            'Persamaan Linier',
            'Sistem Operasi Pengantar',
        ]);

        $pageCount = fake()->numberBetween(8, 25);
        $wordCount = $pageCount * fake()->numberBetween(220, 340);

        return [
            'user_id' => User::factory(),
            'subject_id' => Subject::factory(),
            'title' => $title,
            'original_filename' => Str::slug($title).'.pdf',
            'file_path' => 'documents/dummy/'.Str::uuid()->toString().'.pdf',
            'file_size_bytes' => fake()->numberBetween(100_000, 2_000_000),
            'status' => Document::STATUS_READY,
            'page_count' => $pageCount,
            'word_count' => $wordCount,
            'total_chunks' => 0,
            'extracted_text' => $this->dummyExtractedText($title),
            'error_message' => null,
            'processed_at' => now()->subDays(fake()->numberBetween(0, 14)),
        ];
    }

    /**
     * State: dokumen masih dalam status pending.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => Document::STATUS_PENDING,
            'page_count' => null,
            'word_count' => null,
            'extracted_text' => null,
            'processed_at' => null,
        ]);
    }

    /**
     * State: dokumen gagal diproses dengan pesan error.
     */
    public function failed(string $message = 'PDF rusak atau bukan dokumen teks.'): static
    {
        return $this->state(fn () => [
            'status' => Document::STATUS_FAILED,
            'page_count' => null,
            'word_count' => null,
            'extracted_text' => null,
            'error_message' => $message,
            'processed_at' => now(),
        ]);
    }

    /**
     * Hasilkan teks dummy "hasil ekstraksi" agar tampak nyata di preview.
     */
    private function dummyExtractedText(string $title): string
    {
        return implode(' ', [
            "Bab pertama dari materi {$title} ini membahas konsep dasar yang menjadi fondasi pemahaman selanjutnya.",
            'Pembaca diharapkan menguasai definisi inti, terminologi penting, serta hubungan antar konsep.',
            'Latihan di akhir bab dirancang untuk memperkuat pemahaman melalui kasus-kasus sederhana hingga aplikatif.',
            'Setiap subbagian dilengkapi contoh terselesaikan agar alur penalaran mudah diikuti.',
            'Materi ini juga menjadi prasyarat untuk modul lanjut yang dibahas pada bab berikutnya.',
        ]);
    }
}
