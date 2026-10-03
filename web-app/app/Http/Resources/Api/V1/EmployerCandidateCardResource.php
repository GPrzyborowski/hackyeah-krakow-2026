<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\AnonymousCandidateResource;

/**
 * An anonymous candidate card with the match panel for the employer's swipe view.
 * Reuses the web allowlist on purpose: never add surname, email, photo, CV, leave or due dates here.
 */
class EmployerCandidateCardResource extends AnonymousCandidateResource {}
