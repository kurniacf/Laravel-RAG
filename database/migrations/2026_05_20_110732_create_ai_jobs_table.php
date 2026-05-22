<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();
            // Tipe pekerjaan: parse, chunk, embed.
            $table->string('job_type', 16);
            // Status: pending, running, completed, failed.
            $table->string('status', 16)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'job_type']);
            $table->index('status');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE ai_jobs ADD CONSTRAINT ai_jobs_job_type_check ".
                "CHECK (job_type IN ('parse', 'chunk', 'embed'))"
            );
            DB::statement(
                "ALTER TABLE ai_jobs ADD CONSTRAINT ai_jobs_status_check ".
                "CHECK (status IN ('pending', 'running', 'completed', 'failed'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ai_jobs DROP CONSTRAINT IF EXISTS ai_jobs_job_type_check');
            DB::statement('ALTER TABLE ai_jobs DROP CONSTRAINT IF EXISTS ai_jobs_status_check');
        }

        Schema::dropIfExists('ai_jobs');
    }
};
