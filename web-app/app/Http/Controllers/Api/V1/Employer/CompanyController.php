<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\UpdateCompanyRequest;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Models\Company;
use App\Services\Employer\CompanyRatingSummary;
use Illuminate\Http\Request;

/**
 * The employer's company profile with approved parent reviews.
 */
class CompanyController extends Controller
{
    use InteractsWithEmployerCompany;

    public function __construct(private readonly CompanyRatingSummary $ratingSummary) {}

    public function show(Request $request): CompanyResource
    {
        return $this->present($this->currentCompany($request));
    }

    /**
     * Same rules as the web form: NIP of 10 digits, moderated description.
     */
    public function update(UpdateCompanyRequest $request): CompanyResource
    {
        $company = $this->currentCompany($request);
        $company->update($request->validated());

        return $this->present($company);
    }

    private function present(Company $company): CompanyResource
    {
        $reviews = $this->ratingSummary->reviews($company);

        return new CompanyResource($company, $this->ratingSummary->ratings($company, $reviews), $reviews);
    }
}
