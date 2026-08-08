<?php

namespace App\Http\Controllers;

use App\CollectionBrowse;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrowseController extends Controller
{
    public function __construct(private CollectionBrowse $browse) {}

    public function __invoke(Request $request, ?string $dimension = null): Response
    {
        $dimension ??= 'labels';
        abort_unless(array_key_exists($dimension, CollectionBrowse::DIMENSIONS), 404);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Browse/Index', $this->browse->for($user, $dimension));
    }
}
