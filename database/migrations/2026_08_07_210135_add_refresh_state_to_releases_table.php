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
        Schema::table('releases', function (Blueprint $table) {
            $table->string('refresh_status')->default('idle')->after('fetched_at')->index();
            $table->timestamp('refresh_attempted_at')->nullable()->after('refresh_status');
            $table->timestamp('refresh_failed_at')->nullable()->after('refresh_attempted_at')->index();
            $table->string('refresh_error', 500)->nullable()->after('refresh_failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn([
                'refresh_status',
                'refresh_attempted_at',
                'refresh_failed_at',
                'refresh_error',
            ]);
        });
    }
};
