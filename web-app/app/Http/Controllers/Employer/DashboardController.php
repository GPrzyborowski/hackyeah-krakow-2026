<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Resources\Api\V1\EmployerDashboardResource;
use App\Services\Employer\EmployerDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Employer "Start": KPIs, funnel per published offer, to-do list and recent activity (same data as the mobile API).
     */
    public function __invoke(Request $request, EmployerDashboard $dashboard): Response
    {
        $data = $dashboard->forEmployer($request->user(), $this->currentCompany($request));

        return Inertia::render('employer/Dashboard', (new EmployerDashboardResource($data))->resolve($request));
    }
}
