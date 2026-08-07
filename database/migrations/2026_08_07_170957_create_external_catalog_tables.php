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
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discogs_id')->unique();
            $table->string('name')->index();
            $table->string('real_name')->nullable();
            $table->text('profile')->nullable();
            $table->string('source_url');
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discogs_id')->unique();
            $table->string('name')->index();
            $table->text('profile')->nullable();
            $table->text('contact_info')->nullable();
            $table->string('source_url');
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
        });

        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discogs_id')->unique();
            $table->unsignedBigInteger('master_discogs_id')->nullable()->index();
            $table->string('title')->index();
            $table->string('country')->nullable()->index();
            $table->unsignedSmallInteger('released_year')->nullable()->index();
            $table->string('released')->nullable();
            $table->longText('notes')->nullable();
            $table->string('data_quality')->nullable();
            $table->string('source_url');
            $table->timestamp('fetched_at')->index();
            $table->timestamp('discogs_changed_at')->nullable()->index();
            $table->char('source_hash', 64);
            $table->json('image_urls')->nullable();
            $table->timestamps();
        });

        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('styles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('artist_release', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->foreignId('artist_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('credited_name')->nullable();
            $table->string('join_text')->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->index(['release_id', 'retired_at', 'position']);
            $table->index(['artist_id', 'retired_at']);
        });

        Schema::create('label_release', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->foreignId('label_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('catalog_number')->nullable()->index();
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->index(['release_id', 'retired_at', 'position']);
            $table->index(['label_id', 'retired_at']);
        });

        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->foreignId('artist_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('role');
            $table->string('credited_name')->nullable();
            $table->string('join_text')->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->index(['release_id', 'retired_at', 'position']);
            $table->index(['artist_id', 'role']);
        });

        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('position')->nullable();
            $table->string('title');
            $table->string('duration')->nullable();
            $table->string('type')->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->index(['release_id', 'retired_at', 'sequence']);
        });

        Schema::create('formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->string('text')->nullable();
            $table->json('descriptions')->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->index(['release_id', 'retired_at', 'position']);
            $table->index(['name', 'retired_at']);
        });

        Schema::create('genre_release', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->foreignId('genre_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['release_id', 'genre_id']);
            $table->index(['release_id', 'retired_at', 'position']);
            $table->index(['genre_id', 'retired_at']);
        });

        Schema::create('release_style', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->foreignId('style_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['release_id', 'style_id']);
            $table->index(['release_id', 'retired_at', 'position']);
            $table->index(['style_id', 'retired_at']);
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('uri');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->boolean('embed')->default(false);
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['release_id', 'uri']);
            $table->index(['release_id', 'retired_at', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
        Schema::dropIfExists('release_style');
        Schema::dropIfExists('genre_release');
        Schema::dropIfExists('formats');
        Schema::dropIfExists('tracks');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('label_release');
        Schema::dropIfExists('artist_release');
        Schema::dropIfExists('styles');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('labels');
        Schema::dropIfExists('artists');
    }
};
