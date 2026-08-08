<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReleaseVocabularyRequest;
use App\Models\Release;
use App\Models\User;
use App\ReleaseVocabularyManager;
use Illuminate\Http\RedirectResponse;

class ReleaseVocabularyController extends Controller
{
    public function __construct(private ReleaseVocabularyManager $vocabulary) {}

    public function update(
        UpdateReleaseVocabularyRequest $request,
        Release $release,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        /** @var array{tag_ids: array<int, int>, release_riddim_id: int|null, track_riddim_overrides: array<int, array{sequence: int, riddim_id: int}>} $validated */
        $validated = $request->validated();

        $this->vocabulary->update($user, $release, $validated);

        return back();
    }
}
