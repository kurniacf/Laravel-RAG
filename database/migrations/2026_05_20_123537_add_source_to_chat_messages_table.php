<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Klasifikasi sumber jawaban assistant:
        //   document  → dijawab berbasis konteks dokumen (default lama).
        //   general   → dijawab dari pengetahuan umum (di luar dokumen).
        //   refused   → AI menolak (di luar topik, manipulasi, tidak ditemukan).
        // Pesan dari user juga akan mengisi 'document' sebagai default (kosong
        // semantically — kolom hanya bermakna untuk role=assistant).
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('source', 16)->default('document')->after('content');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_source_check ".
                "CHECK (source IN ('document', 'general', 'refused'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE chat_messages DROP CONSTRAINT IF EXISTS chat_messages_source_check');
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
