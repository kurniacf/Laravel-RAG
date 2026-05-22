<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel ringkasan otomatis (Tier 2 — Auto-Summary).
     *
     * Satu dokumen punya maksimal tiga baris di sini, satu per `type`.
     * Constraint unique (document_id, type) membuat "Buat ulang" cukup
     * me-replace baris yang ada lewat updateOrCreate.
     */
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();
            // Tipe ringkasan: executive, per_chapter, key_points.
            $table->string('type', 16);
            // Isi ringkasan (teks; per_chapter & key_points memakai format ringan).
            $table->text('content');
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->string('model_used')->nullable();
            $table->timestamps();

            // Satu ringkasan per tipe per dokumen.
            $table->unique(['document_id', 'type']);
        });

        // CHECK constraint hanya di PostgreSQL (SQLite testing tidak butuh).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE summaries ADD CONSTRAINT summaries_type_check '.
                "CHECK (type IN ('executive', 'per_chapter', 'key_points'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE summaries DROP CONSTRAINT IF EXISTS summaries_type_check');
        }

        Schema::dropIfExists('summaries');
    }
};
