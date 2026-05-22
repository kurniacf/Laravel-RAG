<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->unsignedInteger('page_number')->nullable();
            $table->unsignedInteger('token_count')->nullable();
            // Kolom embedding ditambahkan terpisah karena tipe vector hanya
            // tersedia di PostgreSQL. SQLite (test) memakai TEXT (JSON).
            $table->timestamps();

            $table->unique(['document_id', 'chunk_index']);
        });

        // Tambah kolom embedding sesuai driver database.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(768)');
            // HNSW index untuk pencarian similarity dengan cosine distance.
            // Aman dibuat di table kosong; index akan terbangun seiring data masuk.
            DB::statement(
                'CREATE INDEX document_chunks_embedding_hnsw_idx '.
                'ON document_chunks USING hnsw (embedding vector_cosine_ops)'
            );
        } else {
            // SQLite: simpan vector sebagai TEXT (JSON-encoded array).
            Schema::table('document_chunks', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_hnsw_idx');
        }

        Schema::dropIfExists('document_chunks');
    }
};
