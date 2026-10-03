<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    /**
     * Contact address shown on the static legal pages.
     */
    public const string CONTACT_EMAIL = 'kontakt@momjobs.test';

    /**
     * Terms of service (Regulamin serwisu).
     */
    public function terms(): Response
    {
        return Inertia::render('public/legal/Terms', [
            'contactEmail' => self::CONTACT_EMAIL,
        ]);
    }

    /**
     * Privacy policy (Polityka prywatności).
     */
    public function privacy(): Response
    {
        return Inertia::render('public/legal/Privacy', [
            'contactEmail' => self::CONTACT_EMAIL,
        ]);
    }

    /**
     * Contact information and FAQ.
     */
    public function contact(): Response
    {
        return Inertia::render('public/legal/Contact', [
            'contactEmail' => self::CONTACT_EMAIL,
        ]);
    }
}
