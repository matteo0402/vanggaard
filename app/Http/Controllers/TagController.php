<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use App\Models\User;
use App\VocabularyManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TagController extends Controller
{
    public function __construct(private VocabularyManager $vocabulary) {}

    public function store(StoreTagRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $this->vocabulary->create($user, new Tag, $validated['name']);

        return back();
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $this->vocabulary->rename($tag, $validated['name']);

        return back();
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $this->vocabulary->delete($tag);

        return back();
    }
}
