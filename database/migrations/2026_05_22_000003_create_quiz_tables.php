<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skema fitur Kuis (Tier 2). Lima tabel saling terkait dibuat dalam satu
     * migration agar urutan foreign key terjaga dan mudah dirawat sebagai unit:
     *
     *  quizzes        — satu kuis dihasilkan dari satu dokumen.
     *  quiz_questions — soal milik kuis; source_chunk_id untuk traceability.
     *  quiz_options   — pilihan jawaban (mcq: 4, true_false: 2, short_answer: 0).
     *  quiz_attempts  — satu sesi pengerjaan kuis oleh user (Fase 3).
     *  quiz_answers   — jawaban user per soal dalam satu attempt (Fase 3).
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Tingkat kesulitan kuis: easy, medium, hard.
            $table->string('difficulty', 16);
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->unsignedInteger('total_attempts')->default(0);
            // Rata-rata skor (persen 0-100) dari attempt yang selesai.
            $table->unsignedTinyInteger('average_score')->nullable();
            $table->timestamps();

            $table->index('document_id');
            $table->index('user_id');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            // Chunk asal soal — null bila chunk dihapus, soal tetap dipertahankan.
            $table->foreignId('source_chunk_id')->nullable()
                ->constrained('document_chunks')->nullOnDelete();
            // Tipe soal: mcq, true_false, short_answer.
            $table->string('type', 16);
            $table->text('question_text');
            // Jawaban benar dalam bentuk teks (tf: 'Benar'/'Salah'; sa: teks; mcq: teks opsi benar).
            $table->text('correct_answer');
            $table->text('explanation')->nullable();
            $table->string('difficulty', 16);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('quiz_id');
        });

        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('quiz_question_id');
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Status: in_progress, completed.
            $table->string('status', 16)->default('in_progress');
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->unsignedSmallInteger('total_questions')->nullable();
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'user_id']);
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            // Opsi yang dipilih (mcq/true_false). Null untuk short_answer.
            $table->foreignId('selected_option_id')->nullable()
                ->constrained('quiz_options')->nullOnDelete();
            // Jawaban teks bebas (short_answer). Null untuk mcq/true_false.
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->index('quiz_attempt_id');
        });

        // CHECK constraint hanya di PostgreSQL.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_difficulty_check CHECK (difficulty IN ('easy', 'medium', 'hard'))");
            DB::statement("ALTER TABLE quiz_questions ADD CONSTRAINT quiz_questions_type_check CHECK (type IN ('mcq', 'true_false', 'short_answer'))");
            DB::statement("ALTER TABLE quiz_questions ADD CONSTRAINT quiz_questions_difficulty_check CHECK (difficulty IN ('easy', 'medium', 'hard'))");
            DB::statement("ALTER TABLE quiz_attempts ADD CONSTRAINT quiz_attempts_status_check CHECK (status IN ('in_progress', 'completed'))");
        }
    }

    public function down(): void
    {
        // Drop dalam urutan terbalik karena ketergantungan foreign key.
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
