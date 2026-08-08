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
        Schema::table('personal_release_metadata', function (Blueprint $table) {
            $table->boolean('is_year_approximate')->default(false);
            $table->boolean('is_favourite')->default(false)->index();
            $table->boolean('is_dj_ready')->default(false)->index();
            $table->unsignedTinyInteger('energy')->nullable()->index();
            $table->decimal('bpm', 6, 2)->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_release_metadata', function (Blueprint $table) {
            $table->dropColumn([
                'is_year_approximate',
                'is_favourite',
                'is_dj_ready',
                'energy',
                'bpm',
            ]);
        });
    }
};
