<?php

namespace App\Http\Controllers\JobSharing;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobSharing\StorePairMessageRequest;
use App\Models\JobSharePair;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PairMessageController extends Controller
{
    /**
     * Message in the pair's private chat; it is between candidates only, so it is not moderated.
     */
    public function store(StorePairMessageRequest $request, JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('chat', $pair);

        $pair->messages()->create([
            'user_id' => $request->user()?->id,
            'body' => $request->string('body')->trim()->toString(),
        ]);

        return back();
    }
}
