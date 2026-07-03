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
            $table->string('fastapi_username')->nullable()->after('status');
        });

        Schema::table('vpn_devices', function (Blueprint $table) {
            $table->string('device_identifier')->nullable()->after('vpn_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fastapi_username');
        });

        Schema::table('vpn_devices', function (Blueprint $table) {
            $table->dropColumn('device_identifier');
        });
    }
};
