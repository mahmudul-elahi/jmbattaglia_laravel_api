<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';
}
