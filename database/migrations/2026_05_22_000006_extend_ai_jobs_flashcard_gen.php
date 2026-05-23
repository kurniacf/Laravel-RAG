<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tier 3 menambah jenis pekerjaan AI `flashcard_gen`. CHECK constraint
     * `job_type` di tabel ai_jobs diperluas agar PostgreSQL menerimanya.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE ai_jobs DROP CONSTRAINT IF EXISTS ai_jobs_job_type_check');
        DB::statement(
            'ALTER TABLE ai_jobs ADD CONSTRAINT ai_jobs_job_type_check '.
            "CHECK (job_type IN ('parse', 'chunk', 'embed', 'summarize', 'quiz_gen', 'flashcard_gen'))"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE ai_jobs DROP CONSTRAINT IF EXISTS ai_jobs_job_type_check');
        DB::statement(
            'ALTER TABLE ai_jobs ADD CONSTRAINT ai_jobs_job_type_check '.
            "CHECK (job_type IN ('parse', 'chunk', 'embed', 'summarize', 'quiz_gen'))"
        );
    }
};
