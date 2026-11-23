<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MENGGANTIKAN berkas bawaan Laravel/Breeze (0001_01_01_000000_create_users_table.php).
// Hapus berkas bawaan itu, lalu pakai berkas ini.
// Tidak ada email dan reset kata sandi: pengguna dibuat oleh Administrator.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('name', 100);
            $table->string('password');                       // berisi hash (bcrypt), padanan password_hash di laporan
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_until')->nullable();    // penguncian sementara
            $table->rememberToken();
            $table->timestamps();
        });

        // Dipakai bila SESSION_DRIVER=database
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
