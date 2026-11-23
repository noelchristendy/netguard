<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_logs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('timestamp')->index();                 // waktu trafik (dari CSV/input), bukan waktu insert
            $table->string('src_ip', 45)->index();
            $table->string('dst_ip', 45);
            $table->string('protocol', 4);
            $table->unsignedSmallInteger('dst_port')->nullable();   // null untuk ICMP
            $table->enum('decision', ['allow', 'deny'])->index();
            $table->foreignId('rule_id')->nullable()->constrained('rules')->nullOnDelete(); // rule penentu
            $table->boolean('is_default')->default(false);          // true bila default deny
            $table->json('logged_rule_ids')->nullable();            // rule aksi "log" yang terlewati
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_logs');
    }
};
