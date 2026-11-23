<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('priority')->unique();          // kecil dievaluasi lebih dulu
            $table->string('src_ip', 18)->nullable();               // IP atau CIDR; null = semua
            $table->string('dst_ip', 18)->nullable();
            $table->string('protocol', 4)->default('any');          // TCP | UDP | ICMP | any
            $table->unsignedSmallInteger('dst_port')->nullable();   // null = semua
            $table->time('time_start')->nullable();                 // jendela boleh melewati tengah malam
            $table->time('time_end')->nullable();
            $table->enum('action', ['allow', 'deny', 'log']);
            $table->boolean('enabled')->default(true);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
