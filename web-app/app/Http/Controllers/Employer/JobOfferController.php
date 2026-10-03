<?php

namespace App\Http\Controllers\Employer;

use App\Enums\EmploymentFraction;
use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Enums\OfferStatus;
use App\Enums\SkillImportance;
use App\Enums\WorkMode;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\SaveJobOfferRequest;
use App\Http\Resources\EmployerJobOfferResource;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JobOfferController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * The company's offers with candidate funnel counters.
     */
    public function index(Request $request, MatchScorer $scorer): Response
    {
        Gate::authorize('viewAny', JobOffer::class);

        $company = $this->currentCompany($request);
        $hasApprovedReview = $company->approvedReviews()->exists();

        $offers = $company->jobOffers()
            ->with(['skills', 'invitations', 'company'])
            ->withCount(['jobSharePairs as submitted_pairs_count' => fn ($query) => $query->where('status', JobSharePairStatus::Submitted)])
            ->orderByRaw('case status when ? then 0 when ? then 1 else 2 end', [OfferStatus::Published->value, OfferStatus::Draft->value])
            ->latest()
            ->get()
            ->map(fn (JobOffer $offer): array => [
                ...(new EmployerJobOfferResource($offer))->resolve($request),
                'statistics' => $this->offerStatistics($offer, $scorer),
                'submitted_pairs_count' => (int) $offer->getAttribute('submitted_pairs_count'),
                'is_parent_friendly' => $offer->salary_min !== null && $offer->salary_max !== null && $offer->flexible_hours && $hasApprovedReview,
            ]);

        return Inertia::render('employer/offers/Index', [
            'offers' => $offers,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', JobOffer::class);

        return $this->renderForm($this->currentCompany($request), null);
    }

    public function store(SaveJobOfferRequest $request): RedirectResponse
    {
        Gate::authorize('create', JobOffer::class);

        $offer = $this->currentCompany($request)->jobOffers()->make();

        $this->persist($offer, $request);

        return $this->redirectAfterSave($offer, $request);
    }

    public function edit(Request $request, JobOffer $offer): Response
    {
        Gate::authorize('update', $offer);

        return $this->renderForm($this->currentCompany($request), $offer->load('skills'));
    }

    public function update(SaveJobOfferRequest $request, JobOffer $offer): RedirectResponse
    {
        Gate::authorize('update', $offer);

        $this->persist($offer, $request);

        return $this->redirectAfterSave($offer, $request);
    }

    /**
     * Close the offer and withdraw invitations nobody answered yet.
     */
    public function close(JobOffer $offer): RedirectResponse
    {
        Gate::authorize('close', $offer);

        DB::transaction(function () use ($offer): void {
            $offer->update(['status' => OfferStatus::Closed]);
            $offer->invitations()->where('status', InvitationStatus::Pending)->update(['status' => InvitationStatus::Withdrawn]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Oferta została zamknięta.']);

        return to_route('employer.offers.index');
    }

    private function renderForm(Company $company, ?JobOffer $offer): Response
    {
        return Inertia::render('employer/offers/Form', [
            'offer' => $offer ? (new EmployerJobOfferResource($offer))->resolve() : null,
            'companyHasApprovedReview' => $company->approvedReviews()->exists(),
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'employmentFractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
        ]);
    }

    private function persist(JobOffer $offer, SaveJobOfferRequest $request): void
    {
        DB::transaction(function () use ($offer, $request): void {
            $offer->fill([
                ...$request->safe()->only(['title', 'city', 'work_mode', 'start_date', 'description', 'employment_fraction', 'salary_min', 'salary_max']),
                'flexible_hours' => $request->boolean('flexible_hours'),
                'fixed_meeting_hours' => $request->boolean('fixed_meeting_hours'),
                'childcare_subsidy' => $request->boolean('childcare_subsidy'),
                'is_job_share' => $request->boolean('is_job_share'),
                'workday_starts_at' => $request->boolean('is_job_share') ? $request->validated('workday_starts_at') : null,
                'workday_ends_at' => $request->boolean('is_job_share') ? $request->validated('workday_ends_at') : null,
                'status' => $request->isPublishing() ? OfferStatus::Published : OfferStatus::Draft,
            ]);

            if ($request->isPublishing() && $offer->published_at === null) {
                $offer->published_at = now();
            }

            $offer->save();

            $skills = [];

            foreach ($request->niceToHaveSkillNames() as $name) {
                $skills[Skill::findOrCreateByName($name)->id] = ['importance' => SkillImportance::NiceToHave->value];
            }

            foreach ($request->requiredSkillNames() as $name) {
                $skills[Skill::findOrCreateByName($name)->id] = ['importance' => SkillImportance::Required->value];
            }

            $offer->skills()->sync($skills);
        });
    }

    private function redirectAfterSave(JobOffer $offer, SaveJobOfferRequest $request): RedirectResponse
    {
        if ($request->isPublishing()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Oferta opublikowana. Oto pasujące kandydatki.']);

            return to_route('employer.candidates.index', ['offer' => $offer->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Szkic zapisany.']);

        return to_route('employer.offers.edit', $offer);
    }
}
