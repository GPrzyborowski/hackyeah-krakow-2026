<?php

namespace App\Enums;

enum SkillImportance: string
{
    case Required = 'required';
    case NiceToHave = 'nice_to_have';
}
