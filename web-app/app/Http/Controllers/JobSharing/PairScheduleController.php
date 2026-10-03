<?php

namespace App\Http\Controllers\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobSharing\UpdatePairScheduleRequest;
use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Services\JobSharing\PairPresenter;
use App\Services\JobSharing\ScheduleValidator;
use App\Services\JobSharing\Workday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PairScheduleController extends Controller
{
    use ResolvesCandidateProfile;

    public function __construct(private readonly PairPresenter $presenter) {}

    /**
     * Save a new proposal of the day split; both members have to accept it again.
     */
    public function update(UpdatePairScheduleRequest $request, JobSharePair $pair, ScheduleValidator $validator): RedirectResponse
    {
        Gate::authorize('planSchedule', $pair);

        $members = $this->presenter->members($pair);
        $blocks = $request->blocks();

        $errors = $validator->errors(
            Workday::forOffer($pair->jobOffer),
            array_values($members->map(fn (CandidateProfile $member): int => $member->id)->all()),
            $blocks,
        );

        if ($errors !== []) {
            throw ValidationException::withMessages(['schedule' => $errors[0]]);
        }

        DB::transaction(function () use ($pair, $members, $blocks): void {
            $pair->update(['proposed_schedule' => $blocks]);

            foreach ($members as $member) {
                $pair->members()->updateExistingPivot($member->id, ['schedule_confirmed_at' => null]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Podział zapisany. Teraz obie zaakceptujcie go.']);

        return back();
    }

    /**
     * The signed-in member accepts the current proposal.
     */
    public function confirm(Request $request, JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('planSchedule', $pair);

        if ($pair->proposed_schedule === null) {
            throw ValidationException::withMessages(['schedule' => 'Najpierw zapiszcie propozycję podziału dnia.']);
        }

        $pair->members()->updateExistingPivot($this->candidateProfile($request)->id, ['schedule_confirmed_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaakceptowałaś podział dnia.']);

        return back();
    }

    /**
     * Send the pair to the employer once both members accepted the split.
     */
    public function submit(JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('planSchedule', $pair);

        $members = $this->presenter->members($pair);
        $everyoneConfirmed = $members->count() === JobSharePair::MAX_MEMBERS
            && $members->every(fn (CandidateProfile $member): bool => $this->presenter->hasAccepted($member) && $this->presenter->hasConfirmedSchedule($member));

        if ($pair->proposed_schedule === null || ! $everyoneConfirmed) {
            throw ValidationException::withMessages(['schedule' => 'Obie osoby muszą zaakceptować podział, zanim wyślecie go pracodawcy.']);
        }

        $pair->update(['status' => JobSharePairStatus::Submitted, 'submitted_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Wysłane! Pracodawca zobaczy Was jako parę – nadal anonimowo.']);

        return back();
    }
}
