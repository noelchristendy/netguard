<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp')->useCurrent()->index();
            // null bila pelaku tidak dikenal (mis. login gagal dengan username yang tidak ada;
            // username yang dicoba ditulis di kolom detail)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->index();                  // LOGIN_SUCCESS, RULE_UPDATED, dst.
            $table->text('detail')->nullable();                     // JSON nilai lama/baru atau keterangan
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
