<?php

namespace App\Enums;

enum CvStatus: string
{
    case Uploaded = 'uploaded';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case Failed = 'failed';
}
