<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tier 2 menambah dua jenis pekerjaan AI baru: `summarize` (Auto-Summary)
     * dan `quiz_gen` (Quiz Generator). CHECK constraint `job_type` di tabel
     * ai_jobs harus diperluas agar PostgreSQL menerima nilai baru ini.
     *
     * Kedua nilai ditambahkan sekaligus supaya tidak perlu mengubah constraint
     * dua kali di Fase 1 dan Fase 2.
     */
    public function up(): void
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

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE ai_jobs DROP CONSTRAINT IF EXISTS ai_jobs_job_type_check');
        DB::statement(
            'ALTER TABLE ai_jobs ADD CONSTRAINT ai_jobs_job_type_check '.
            "CHECK (job_type IN ('parse', 'chunk', 'embed'))"
        );
    }
};
