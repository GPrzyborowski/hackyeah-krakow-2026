<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
}
