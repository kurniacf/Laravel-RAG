<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            // Role: 'user' (pertanyaan) atau 'assistant' (jawaban AI).
            $table->string('role', 16);
            $table->text('content');
            // ID chunk yang dijadikan referensi citation (hanya untuk assistant).
            $table->json('cited_chunk_ids')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->timestamps();

            $table->index(['chat_session_id', 'created_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_role_check ".
                "CHECK (role IN ('user', 'assistant'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE chat_messages DROP CONSTRAINT IF EXISTS chat_messages_role_check');
        }

        Schema::dropIfExists('chat_messages');
    }
};
