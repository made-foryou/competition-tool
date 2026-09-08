<?php

namespace App\Enums;

enum CompetitionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Finished = 'finished';
}
