<?php

namespace App\Http\Controllers;

use App\Events\PersonalMetadataChanged;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TagController extends Controller
{
    public function store(StoreTagRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->tags()->create($request->validated());

        return back();
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        DB::transaction(function () use ($request, $tag): void {
            $releaseIds = DB::table('release_tag')
                ->where('user_id', $tag->user_id)
                ->where('tag_id', $tag->id)
                ->pluck('release_id');

            $tag->update($request->validated());

            $releaseIds->each(fn (int $releaseId) => PersonalMetadataChanged::dispatch(
                $tag->user_id,
                $releaseId,
            ));
        });

        return back();
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        DB::transaction(function () use ($tag): void {
            $releaseIds = DB::table('release_tag')
                ->where('user_id', $tag->user_id)
                ->where('tag_id', $tag->id)
                ->pluck('release_id');
            $userId = $tag->user_id;

            $tag->delete();

            $releaseIds->each(fn (int $releaseId) => PersonalMetadataChanged::dispatch(
                $userId,
                $releaseId,
            ));
        });

        return back();
    }
}
