<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePersonalReleaseMetadataRequest;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PersonalReleaseMetadataController extends Controller
{
    public function __invoke(
        UpdatePersonalReleaseMetadataRequest $request,
        Release $release,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $user->personalReleaseMetadata()->updateOrCreate(
            ['release_id' => $release->id],
            $request->validated(),
        );

        return back();
    }
}
