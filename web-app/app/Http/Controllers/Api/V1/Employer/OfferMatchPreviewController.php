<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Actions\Employer\PreviewOfferMatches;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\PreviewMatchesRequest;
use Illuminate\Http\JsonResponse;

class OfferMatchPreviewController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Live "Pasujące kandydatki" counters while the offer is being edited.
     */
    public function __invoke(PreviewMatchesRequest $request, PreviewOfferMatches $previewOfferMatches): JsonResponse
    {
        return response()->json([
            'data' => $previewOfferMatches->handle(
                $this->currentCompany($request),
                $request->validated('start_date'),
                $request->validated('required_skills'),
                $request->validated('nice_to_have_skills'),
            ),
        ]);
    }
}
