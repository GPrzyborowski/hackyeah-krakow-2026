<?php

namespace App\Http\Controllers;

use App\Models\CandidateProfile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a candidate's photo from the private disk to the candidate herself or a company whose invitation she accepted.
 * Shared by the web (session) and the mobile API (Sanctum token) routes.
 */
class CandidatePhotoController extends Controller
{
    public function __invoke(CandidateProfile $profile): StreamedResponse
    {
        Gate::authorize('viewPhoto', $profile);

        $disk = Storage::disk('local');

        abort_if($profile->photo_path === null || ! $disk->exists($profile->photo_path), 404);

        return $disk->response($profile->photo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
