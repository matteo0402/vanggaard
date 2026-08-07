<?php

namespace App\Http\Controllers;

use App\DiscogsSynchronizationStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private DiscogsSynchronizationStatus $status) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render(
            'Welcome',
            $this->status->for($user, max(1, $request->integer('refresh_page', 1))),
        );
    }
}
