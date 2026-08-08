<?php

namespace App;

use App\Events\PersonalMetadataChanged;
use App\Models\Release;
use App\Models\ReleaseRiddim;
use App\Models\ReleaseTag;
use App\Models\TrackRiddimOverride;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReleaseVocabularyManager
{
    /**
     * @param  array{
     *     tag_ids: array<int, int>,
     *     release_riddim_id: int|null,
     *     track_riddim_overrides: array<int, array{sequence: int, riddim_id: int}>
     * }  $data
     */
    public function update(User $user, Release $release, array $data): void
    {
        DB::transaction(function () use ($user, $release, $data): void {
            $now = now();

            ReleaseTag::query()
                ->whereBelongsTo($user)
                ->whereBelongsTo($release)
                ->delete();

            $releaseTags = collect($data['tag_ids'])->map(fn (int $tagId): array => [
                'user_id' => $user->id,
                'release_id' => $release->id,
                'tag_id' => $tagId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($releaseTags->isNotEmpty()) {
                ReleaseTag::query()->insert($releaseTags->all());
            }

            ReleaseRiddim::query()
                ->whereBelongsTo($user)
                ->whereBelongsTo($release)
                ->delete();

            if ($data['release_riddim_id'] !== null) {
                ReleaseRiddim::query()->create([
                    'user_id' => $user->id,
                    'release_id' => $release->id,
                    'riddim_id' => $data['release_riddim_id'],
                ]);
            }

            TrackRiddimOverride::query()
                ->whereBelongsTo($user)
                ->whereBelongsTo($release)
                ->delete();

            $trackOverrides = collect($data['track_riddim_overrides'])
                ->map(fn (array $override): array => [
                    'user_id' => $user->id,
                    'release_id' => $release->id,
                    'track_sequence' => $override['sequence'],
                    'riddim_id' => $override['riddim_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($trackOverrides->isNotEmpty()) {
                TrackRiddimOverride::query()->insert($trackOverrides->all());
            }

            PersonalMetadataChanged::dispatch($user->id, $release->id);
        });
    }
}
