<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRiddimRequest;
use App\Http\Requests\UpdateRiddimRequest;
use App\Models\Riddim;
use App\Models\User;
use App\VocabularyManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RiddimController extends Controller
{
    public function __construct(private VocabularyManager $vocabulary) {}

    public function store(StoreRiddimRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $this->vocabulary->create($user, new Riddim, $validated['name']);

        return back();
    }

    public function update(UpdateRiddimRequest $request, Riddim $riddim): RedirectResponse
    {
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $this->vocabulary->rename($riddim, $validated['name']);

        return back();
    }

    public function destroy(Riddim $riddim): RedirectResponse
    {
        Gate::authorize('delete', $riddim);

        $this->vocabulary->delete($riddim);

        return back();
    }
}
