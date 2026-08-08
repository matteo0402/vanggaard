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
            $table->string('normalized_name')->nullable(false)->change();
            $table->unique(['user_id', 'normalized_name']);
        });

        Schema::table('riddims', function (Blueprint $table) {
            $table->string('normalized_name')->nullable(false)->change();
            $table->unique(['user_id', 'normalized_name']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'normalized_name']);
            $table->string('normalized_name')->nullable()->change();
        });

        Schema::table('riddims', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'normalized_name']);
            $table->string('normalized_name')->nullable()->change();
        });
    }
};
