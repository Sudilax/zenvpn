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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('device_limit')->default(3)->after('remember_token');
            $table->unsignedBigInteger('data_used_mb')->default(0)->after('device_limit');
            $table->unsignedBigInteger('data_cap_mb')->default(51200)->after('data_used_mb'); // 50 GB
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['device_limit', 'data_used_mb', 'data_cap_mb']);
        });
    }
};
