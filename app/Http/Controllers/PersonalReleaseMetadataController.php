<?php

namespace App\Http\Controllers;

use App\Events\PersonalMetadataChanged;
use App\Http\Requests\UpdatePersonalReleaseMetadataRequest;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PersonalReleaseMetadataController extends Controller
{
    public function __invoke(
        UpdatePersonalReleaseMetadataRequest $request,
        Release $release,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user, $release, $request): void {
            $user->personalReleaseMetadata()->updateOrCreate(
                ['release_id' => $release->id],
                $request->validated(),
            );

            PersonalMetadataChanged::dispatch($user->id, $release->id);
        });

        return back();
    }
}
