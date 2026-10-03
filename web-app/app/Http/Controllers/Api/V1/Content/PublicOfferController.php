<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicOfferDetailResource;
use App\Http\Resources\Api\V1\PublicOfferResource;
use App\Models\JobOffer;
use App\Services\Offers\PublicOfferSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Guest-accessible offers (no match score, no candidate data), same filters as the web offers page.
 */
class PublicOfferController extends Controller
{
    private const int PER_PAGE = 20;

    public function index(Request $request, PublicOfferSearch $search): AnonymousResourceCollection
    {
        $filters = $search->filters($request);

        $offers = $search->query($filters)->paginate(self::PER_PAGE)->withQueryString();

        return PublicOfferResource::collection($offers)->additional(['meta' => ['filters' => $filters]]);
    }

    /**
     * A published offer; drafts and closed offers are 404.
     */
    public function show(JobOffer $offer): PublicOfferDetailResource
    {
        abort_unless($offer->isPublished(), 404);

        $offer->load(['skills', 'company.approvedReviews' => fn ($query) => $query->orderBy('id')]);

        return new PublicOfferDetailResource($offer);
    }
}
