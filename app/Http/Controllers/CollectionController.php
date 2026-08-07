<?php

namespace App\Http\Controllers;

use App\CollectionCatalog;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function __construct(private CollectionCatalog $catalog) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('q')->trim()->value() ?: null;

        return Inertia::render('Collection/Index', [
            'releases' => $this->catalog->for($user, $search, max(1, $request->integer('page', 1))),
            'filters' => ['q' => $search],
        ]);
    }

    public function show(Request $request, Release $release): Response
    {
        Gate::authorize('view', $release);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Collection/Show', [
            'release' => $this->catalog->release($user, $release),
        ]);
    }
}
