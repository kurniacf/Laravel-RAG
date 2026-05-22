<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Nama ikon (referensi internal, mis. 'book', 'beaker').
            $table->string('icon', 32)->nullable();
            // Warna hex untuk badge (mis. '#059669').
            $table->string('color_hex', 7)->default('#059669');
            // Denormalisasi: total dokumen di mata pelajaran ini.
            $table->unsignedInteger('documents_count')->default(0);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
