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
        Schema::table('discogs_collection_instances', function (Blueprint $table) {
            $table->foreignId('missing_confirmed_sync_run_id')
                ->nullable()
                ->after('last_seen_sync_run_id');

            $table->index(
                ['discogs_account_id', 'last_seen_sync_run_id', 'id'],
                'instances_reconciliation_candidates_index',
            );
            $table->index(
                ['discogs_account_id', 'missing_confirmed_sync_run_id'],
                'instances_missing_confirmation_index',
            );
            $table->foreign(
                ['missing_confirmed_sync_run_id', 'discogs_account_id'],
                'instances_missing_sync_account_foreign',
            )->references(['id', 'discogs_account_id'])->on('discogs_sync_runs')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discogs_collection_instances', function (Blueprint $table) {
            $table->dropForeign('instances_missing_sync_account_foreign');
            $table->dropIndex('instances_reconciliation_candidates_index');
            $table->dropIndex('instances_missing_confirmation_index');
            $table->dropColumn('missing_confirmed_sync_run_id');
        });
    }
};
