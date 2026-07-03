<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vpn_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_name');
            $table->uuid('vless_uuid');
            $table->uuid('trojan_uuid');
            $table->uuid('vmess_uuid');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vpn_devices');
    }
};
