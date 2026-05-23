<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skema fitur Flashcard (Tier 3).
     *
     *  flashcards        — kartu (sisi depan/belakang) dihasilkan dari dokumen.
     *  flashcard_reviews — state Spaced Repetition (SM-2) per user per kartu.
     *
     * Dibuat satu migration agar urutan FK terjaga & mudah dirawat sebagai unit.
     */
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Chunk asal kartu — null bila chunk dihapus, kartu tetap dipertahankan.
            $table->foreignId('source_chunk_id')->nullable()
                ->constrained('document_chunks')->nullOnDelete();
            // Sisi depan (istilah/pertanyaan) & belakang (definisi/jawaban).
            $table->text('front_text');
            $table->text('back_text');
            // Tingkat kesulitan kartu (label dari AI): easy, medium, hard.
            $table->string('difficulty', 16)->default('medium');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('document_id');
            $table->index('user_id');
        });

        Schema::create('flashcard_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flashcard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Parameter algoritma SM-2.
            $table->decimal('ease_factor', 4, 2)->default(2.50);
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedInteger('repetitions')->default(0);
            // Kualitas jawaban terakhir (skala SM-2 0-5).
            $table->unsignedTinyInteger('quality')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();

            // Satu state SRS per user per kartu.
            $table->unique(['flashcard_id', 'user_id']);
            // Query "kartu jatuh tempo" mengandalkan index ini.
            $table->index(['user_id', 'next_review_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE flashcards ADD CONSTRAINT flashcards_difficulty_check CHECK (difficulty IN ('easy', 'medium', 'hard'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_reviews');
        Schema::dropIfExists('flashcards');
    }
};
