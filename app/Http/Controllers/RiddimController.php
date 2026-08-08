<?php

namespace App\Http\Controllers;

use App\Events\PersonalMetadataChanged;
use App\Http\Requests\StoreRiddimRequest;
use App\Http\Requests\UpdateRiddimRequest;
use App\Models\Riddim;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RiddimController extends Controller
{
    public function store(StoreRiddimRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->riddims()->create($request->validated());

        return back();
    }

    public function update(UpdateRiddimRequest $request, Riddim $riddim): RedirectResponse
    {
        DB::transaction(function () use ($request, $riddim): void {
            $releaseIds = $this->affectedReleaseIds($riddim);

            $riddim->update($request->validated());

            $this->dispatchChanges($riddim->user_id, $releaseIds);
        });

        return back();
    }

    public function destroy(Riddim $riddim): RedirectResponse
    {
        Gate::authorize('delete', $riddim);

        DB::transaction(function () use ($riddim): void {
            $releaseIds = $this->affectedReleaseIds($riddim);
            $userId = $riddim->user_id;

            $riddim->delete();

            $this->dispatchChanges($userId, $releaseIds);
        });

        return back();
    }

    /** @return Collection<int, int> */
    private function affectedReleaseIds(Riddim $riddim): Collection
    {
        return DB::table('release_riddim')
            ->where('user_id', $riddim->user_id)
            ->where('riddim_id', $riddim->id)
            ->pluck('release_id')
            ->merge(DB::table('track_riddim_override')
                ->where('user_id', $riddim->user_id)
                ->where('riddim_id', $riddim->id)
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
