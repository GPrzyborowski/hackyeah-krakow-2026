<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicCompanyResource;
use App\Models\Company;

class PublicCompanyController extends Controller
{
    /**
     * Public company profile: approved reviews only and published offers.
     */
    public function show(Company $company): PublicCompanyResource
    {
        $company->load([
            'approvedReviews' => fn ($query) => $query->latest()->latest('id'),
            'jobOffers' => fn ($query) => $query->published()->latest('published_at')->latest('id'),
        ]);

        return new PublicCompanyResource($company);
    }
}
