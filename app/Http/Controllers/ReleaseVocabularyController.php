<?php

namespace App\Http\Controllers;

use App\Events\PersonalMetadataChanged;
use App\Http\Requests\UpdateReleaseVocabularyRequest;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReleaseVocabularyController extends Controller
{
    public function update(
        UpdateReleaseVocabularyRequest $request,
        Release $release,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $release, $validated): void {
            $now = now();

            DB::table('release_tag')
                ->where('user_id', $user->id)
                ->where('release_id', $release->id)
                ->delete();

            if ($validated['tag_ids'] !== []) {
                DB::table('release_tag')->insert(array_map(
                    fn (int $tagId): array => [
                        'user_id' => $user->id,
                        'release_id' => $release->id,
                        'tag_id' => $tagId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $validated['tag_ids'],
                ));
            }

            DB::table('release_riddim')
                ->where('user_id', $user->id)
                ->where('release_id', $release->id)
                ->delete();

            if ($validated['release_riddim_id'] !== null) {
                DB::table('release_riddim')->insert([
                    'user_id' => $user->id,
                    'release_id' => $release->id,
                    'riddim_id' => $validated['release_riddim_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('track_riddim_override')
                ->where('user_id', $user->id)
                ->where('release_id', $release->id)
                ->delete();

            if ($validated['track_riddim_overrides'] !== []) {
                DB::table('track_riddim_override')->insert(array_map(
                    fn (array $override): array => [
                        'user_id' => $user->id,
                        'release_id' => $release->id,
                        'track_sequence' => $override['sequence'],
                        'riddim_id' => $override['riddim_id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $validated['track_riddim_overrides'],
                ));
            }

            PersonalMetadataChanged::dispatch($user->id, $release->id);
        });

        return back();
    }
}
