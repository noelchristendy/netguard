<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nama berkas ini sengaja lebih dulu (urutan abjad) daripada ..._create_users_table
// supaya tabel roles sudah ada ketika users membuat foreign key.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();   // administrator | operator | auditor
            $table->string('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
