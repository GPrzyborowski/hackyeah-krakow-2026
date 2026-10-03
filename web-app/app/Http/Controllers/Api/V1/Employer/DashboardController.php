<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Resources\Api\V1\EmployerDashboardResource;
use App\Services\Employer\EmployerDashboard;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Employer home: KPIs, funnel per published offer, to-do list and recent activity.
     */
    public function __invoke(Request $request, EmployerDashboard $dashboard): EmployerDashboardResource
    {
        return new EmployerDashboardResource($dashboard->forEmployer($request->user(), $this->currentCompany($request)));
    }
}
