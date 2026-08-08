<?php

namespace App;

use App\Events\PersonalMetadataChanged;
use App\Models\ReleaseRiddim;
use App\Models\ReleaseTag;
use App\Models\Riddim;
use App\Models\Tag;
use App\Models\TrackRiddimOverride;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VocabularyManager
{
    public function create(User $user, Tag|Riddim $vocabulary, string $name): void
    {
        $vocabulary->user()->associate($user);
        $vocabulary->name = $name;
        $vocabulary->save();
    }

    public function rename(Tag|Riddim $vocabulary, string $name): void
    {
        DB::transaction(function () use ($vocabulary, $name): void {
            $releaseIds = $this->affectedReleaseIds($vocabulary);

            $vocabulary->update(['name' => $name]);

            $this->dispatchChanges($vocabulary->user_id, $releaseIds);
        });
    }

    public function delete(Tag|Riddim $vocabulary): void
    {
        DB::transaction(function () use ($vocabulary): void {
            $releaseIds = $this->affectedReleaseIds($vocabulary);
            $userId = $vocabulary->user_id;

            $vocabulary->delete();

            $this->dispatchChanges($userId, $releaseIds);
        });
    }

    /** @return Collection<int, int> */
    private function affectedReleaseIds(Tag|Riddim $vocabulary): Collection
    {
        if ($vocabulary instanceof Tag) {
            return ReleaseTag::query()
                ->whereBelongsTo($vocabulary, 'tag')
                ->where('user_id', $vocabulary->user_id)
                ->pluck('release_id');
        }

        return ReleaseRiddim::query()
            ->whereBelongsTo($vocabulary, 'riddim')
            ->where('user_id', $vocabulary->user_id)
            ->pluck('release_id')
            ->merge(TrackRiddimOverride::query()
                ->whereBelongsTo($vocabulary, 'riddim')
                ->where('user_id', $vocabulary->user_id)
                ->pluck('release_id'))
            ->unique()
            ->values();
    }

    /** @param Collection<int, int> $releaseIds */
    private function dispatchChanges(int $userId, Collection $releaseIds): void
    {
        $releaseIds->each(fn (int $releaseId) => PersonalMetadataChanged::dispatch(
            $userId,
            $releaseId,
        ));
    }
}
