<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin flag — controls Filament panel access
            $table->boolean('is_admin')->default(false)->after('remember_token');

            // Account status — allows admin suspend/activate
            $table->string('status')->default('active')->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'status']);
        });
    }
};
