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

        Schema::create('release_tag', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id');
            $table->timestamps();

            $table->primary(['user_id', 'release_id', 'tag_id']);
            $table->foreign(['tag_id', 'user_id'], 'release_tag_owner_foreign')
                ->references(['id', 'user_id'])->on('tags')->cascadeOnDelete();
        });

        Schema::create('release_riddim', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('riddim_id');
            $table->timestamps();

            $table->primary(['user_id', 'release_id']);
            $table->foreign(['riddim_id', 'user_id'], 'release_riddim_owner_foreign')
                ->references(['id', 'user_id'])->on('riddims')->cascadeOnDelete();
        });

        Schema::create('track_riddim_override', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('track_sequence');
            $table->foreignId('riddim_id');
            $table->timestamps();

            $table->primary(['user_id', 'release_id', 'track_sequence']);
            $table->foreign(['riddim_id', 'user_id'], 'track_riddim_owner_foreign')
                ->references(['id', 'user_id'])->on('riddims')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_riddim_override');
        Schema::dropIfExists('release_riddim');
        Schema::dropIfExists('release_tag');

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
