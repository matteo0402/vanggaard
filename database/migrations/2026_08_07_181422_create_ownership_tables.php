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
        Schema::create('discogs_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username')->unique();
            $table->text('personal_access_token');
            $table->string('source_url');
            $table->timestamp('fetched_at')->index();
            $table->timestamps();

            $table->unique(['id', 'user_id']);
        });

        Schema::create('discogs_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discogs_account_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->index();
            $table->string('status')->index();
            $table->boolean('is_full_reconciliation')->default(false);
            $table->unsignedInteger('items_seen')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_removed')->default(0);
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['id', 'discogs_account_id']);
            $table->index(['discogs_account_id', 'status', 'created_at']);
        });

        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->index(['user_id', 'release_id']);
        });

        Schema::create('discogs_collection_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discogs_account_id');
            $table->foreignId('collection_item_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_seen_sync_run_id')->nullable();
            $table->unsignedBigInteger('discogs_instance_id');
            $table->unsignedBigInteger('discogs_folder_id')->nullable()->index();
            $table->string('source_url');
            $table->timestamp('fetched_at')->index();
            $table->timestamps();

            $table->unique(
                ['discogs_account_id', 'discogs_instance_id'],
                'instances_account_instance_unique',
            );
            $table->foreign(['discogs_account_id', 'user_id'], 'instances_account_owner_foreign')
                ->references(['id', 'user_id'])->on('discogs_accounts')->cascadeOnDelete();
            $table->foreign(['collection_item_id', 'user_id'], 'instances_item_owner_foreign')
                ->references(['id', 'user_id'])->on('collection_items')->cascadeOnDelete();
            $table->foreign(['last_seen_sync_run_id', 'discogs_account_id'], 'instances_sync_account_foreign')
                ->references(['id', 'discogs_account_id'])->on('discogs_sync_runs')->restrictOnDelete();
        });

        Schema::create('personal_release_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained()->restrictOnDelete();
            $table->longText('personal_notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable()->index();
            $table->unsignedSmallInteger('corrected_year')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'release_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'name']);
        });

        Schema::create('riddims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'name']);
        });

        Schema::create('collection_item_tag', function (Blueprint $table) {
            $table->foreignId('collection_item_id');
            $table->foreignId('tag_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['collection_item_id', 'tag_id']);
            $table->foreign(['collection_item_id', 'user_id'], 'item_tag_item_owner_foreign')
                ->references(['id', 'user_id'])->on('collection_items')->cascadeOnDelete();
            $table->foreign(['tag_id', 'user_id'], 'item_tag_tag_owner_foreign')
                ->references(['id', 'user_id'])->on('tags')->cascadeOnDelete();
        });

        Schema::create('collection_item_riddim', function (Blueprint $table) {
            $table->foreignId('collection_item_id');
            $table->foreignId('riddim_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['collection_item_id', 'riddim_id']);
            $table->foreign(['collection_item_id', 'user_id'], 'item_riddim_item_owner_foreign')
                ->references(['id', 'user_id'])->on('collection_items')->cascadeOnDelete();
            $table->foreign(['riddim_id', 'user_id'], 'item_riddim_riddim_owner_foreign')
                ->references(['id', 'user_id'])->on('riddims')->cascadeOnDelete();
        });

        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable();
            $table->unsignedBigInteger('parent_scope_id')->storedAs('coalesce(parent_id, 0)');
            $table->string('kind')->index();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->unique(['user_id', 'parent_scope_id', 'kind', 'name'], 'storage_sibling_name_unique');
            $table->index(['user_id', 'parent_id', 'position']);
            $table->index(['parent_id', 'user_id'], 'storage_parent_owner_index');
            $table->foreign(['parent_id', 'user_id'], 'storage_parent_owner_foreign')
                ->references(['id', 'user_id'])->on('storage_locations')->restrictOnDelete();
        });

        Schema::create('collection_item_storage_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_item_id');
            $table->foreignId('storage_location_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('stored_at')->index();
            $table->timestamp('removed_at')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['collection_item_id', 'removed_at', 'stored_at'],
                'storage_assignment_item_history_index',
            );
            $table->index(
                ['storage_location_id', 'removed_at'],
                'storage_assignment_location_active_index',
            );
            $table->foreign(['collection_item_id', 'user_id'], 'storage_assignment_item_owner_foreign')
                ->references(['id', 'user_id'])->on('collection_items')->cascadeOnDelete();
            $table->foreign(['storage_location_id', 'user_id'], 'storage_assignment_location_owner_foreign')
                ->references(['id', 'user_id'])->on('storage_locations')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_item_storage_assignments');
        Schema::dropIfExists('storage_locations');
        Schema::dropIfExists('collection_item_riddim');
        Schema::dropIfExists('collection_item_tag');
        Schema::dropIfExists('riddims');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('personal_release_metadata');
        Schema::dropIfExists('discogs_collection_instances');
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('discogs_sync_runs');
        Schema::dropIfExists('discogs_accounts');
    }
};
