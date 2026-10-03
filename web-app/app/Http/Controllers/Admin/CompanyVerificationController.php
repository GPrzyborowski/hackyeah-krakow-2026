<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Notifications\CompanyVerified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class CompanyVerificationController extends Controller
{
    /**
     * Companies with their NIP and verification state; `?status=unverified` narrows to companies waiting for a check.
     */
    public function index(Request $request): Response
    {
        $onlyUnverified = $request->query('status') === 'unverified';

        $companies = Company::query()
            ->withCount(['members', 'jobOffers'])
            ->when($onlyUnverified, fn ($query) => $query->whereNull('verified_at'))
            ->orderByRaw('verified_at is not null')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/companies/Index', [
            'status' => $onlyUnverified ? 'unverified' : 'all',
            'counts' => [
                'all' => Company::query()->count(),
                'unverified' => Company::query()->whereNull('verified_at')->count(),
            ],
            'companies' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'nip' => $company->nip,
                'city' => $company->city,
                'members_count' => (int) $company->members_count,
                'offers_count' => (int) $company->job_offers_count,
                'verified' => $company->isVerified(),
                'verified_at' => $company->verified_at?->toIso8601String(),
                'created_at' => $company->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Mark the company as verified and tell its members.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        if (! $company->isVerified()) {
            $company->markVerifiedBy($request->user());

            Notification::send($company->members, new CompanyVerified($company));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Firma {$company->name} została zweryfikowana."]);

        return back();
    }

    /**
     * Withdraw the verification (e.g. a NIP mismatch found later).
     */
    public function destroy(Company $company): RedirectResponse
    {
        $company->revokeVerification();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cofnięto weryfikację firmy {$company->name}."]);

        return back();
    }
}
