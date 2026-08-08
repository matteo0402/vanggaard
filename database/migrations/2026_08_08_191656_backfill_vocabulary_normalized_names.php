<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['tags', 'riddims'] as $tableName) {
            DB::table($tableName)
                ->select(['id', 'user_id', 'name'])
                ->chunkById(500, function (Collection $rows) use ($tableName): void {
                    $rows->each(function (object $row) use ($tableName): void {
                        $normalizedName = Str::lower(Str::squish($row->name));
                        $collision = DB::table($tableName)
                            ->where('user_id', $row->user_id)
                            ->where('normalized_name', $normalizedName)
                            ->where('id', '!=', $row->id)
                            ->first(['id', 'name']);

                        if ($collision !== null) {
                            throw new RuntimeException(
                                "Cannot normalize {$tableName} for user {$row->user_id}: "
                                ."\"{$row->name}\" conflicts with \"{$collision->name}\".",
                            );
                        }

                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['normalized_name' => $normalizedName]);
                    });
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['tags', 'riddims'] as $tableName) {
            DB::table($tableName)
                ->select('id')
                ->chunkById(500, function (Collection $rows) use ($tableName): void {
                    DB::table($tableName)
                        ->whereIn('id', $rows->pluck('id'))
                        ->update(['normalized_name' => null]);
                });
        }
    }
};
