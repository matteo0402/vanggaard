<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['tags', 'riddims'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('normalized_name')->nullable();
            });

            $rows = DB::table($tableName)
                ->orderBy('user_id')
                ->orderBy('id')
                ->get(['id', 'user_id', 'name']);

            $rows->each(function (object $row) use ($tableName): void {
                $temporaryName = '__scrum_30_'.$tableName.'_'.$row->id.'__';

                while (DB::table($tableName)
                    ->where('user_id', $row->user_id)
                    ->where('name', $temporaryName)
                    ->exists()) {
                    $temporaryName .= '_';
                }

                DB::table($tableName)->where('id', $row->id)->update(['name' => $temporaryName]);
            });

            $rows->each(function (object $row) use ($tableName): void {
                $displayName = Str::squish($row->name);
                $normalizedName = Str::lower(Str::squish($displayName));
                $suffix = 2;

                while (DB::table($tableName)
                    ->where('user_id', $row->user_id)
                    ->where(function ($query) use ($displayName, $normalizedName): void {
                        $query->where('normalized_name', $normalizedName)
                            ->orWhere('name', $displayName);
                    })
                    ->exists()) {
                    $displayName = Str::squish($row->name).' ('.$suffix.')';
                    $normalizedName = Str::lower($displayName);
                    $suffix++;
                }

                DB::table($tableName)->where('id', $row->id)->update([
                    'name' => $displayName,
                    'normalized_name' => $normalizedName,
                ]);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('normalized_name')->nullable(false)->change();
                $table->unique(['user_id', 'normalized_name']);
            });
        }

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

        foreach (['tags', 'riddims'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['user_id', 'normalized_name']);
                $table->dropColumn('normalized_name');
            });
        }
    }
};
