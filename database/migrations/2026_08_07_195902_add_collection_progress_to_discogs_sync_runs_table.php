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
        Schema::table('discogs_sync_runs', function (Blueprint $table) {
            $table->unsignedInteger('last_completed_page')->default(0)->after('items_removed');
            $table->unsignedInteger('total_pages')->nullable()->after('last_completed_page');
            $table->unsignedInteger('total_items')->nullable()->after('total_pages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discogs_sync_runs', function (Blueprint $table) {
            $table->dropColumn(['last_completed_page', 'total_pages', 'total_items']);
        });
    }
};
