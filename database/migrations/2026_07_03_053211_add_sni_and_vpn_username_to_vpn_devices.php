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
        Schema::table('vpn_devices', function (Blueprint $table) {
            // SNI domain used in VLESS/Trojan/VMess URIs
            $table->string('sni')->default('m.zoom.us')->after('status');

            // Username stored on the FastAPI backend; used for create/delete API calls
            $table->string('vpn_username')->nullable()->unique()->after('sni');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vpn_devices', function (Blueprint $table) {
            $table->dropColumn(['sni', 'vpn_username']);
        });
    }
};
