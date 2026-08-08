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
        Schema::table('tags', function (Blueprint $table) {
            $table->string('normalized_name')->nullable();
        });

        Schema::table('riddims', function (Blueprint $table) {
            $table->string('normalized_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('normalized_name');
        });

        Schema::table('riddims', function (Blueprint $table) {
            $table->dropColumn('normalized_name');
        });
    }
};
