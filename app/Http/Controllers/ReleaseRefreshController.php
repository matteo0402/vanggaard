<?php

namespace App\Http\Controllers;

use App\DiscogsReleaseRefresher;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReleaseRefreshController extends Controller
{
    public function __construct(private DiscogsReleaseRefresher $refresher) {}

    public function __invoke(Request $request, Release $release): RedirectResponse
    {
        Gate::authorize('refresh', $release);

        /** @var User $user */
        $user = $request->user();
        $account = $user->discogsAccount()->firstOrFail();
        $this->refresher->queue(
            $account,
            $release,
            (string) config('services.discogs.high_priority_queue'),
        );

        return back();
    }
}
