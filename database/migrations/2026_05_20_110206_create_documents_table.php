<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size_bytes');
            // pending | processing | ready | failed.
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedInteger('total_chunks')->default(0);
            $table->text('extracted_text')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('subject_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE documents ADD CONSTRAINT documents_status_check ".
                "CHECK (status IN ('pending', 'processing', 'ready', 'failed'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check');
        }

        Schema::dropIfExists('documents');
    }
};
