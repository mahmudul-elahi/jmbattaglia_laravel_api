<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Processing = 'PROCESSING';
    case Submitted = 'SUBMITTED';
    case Failed = 'FAILED';
    case RequiresReview = 'REQUIRES_REVIEW';
}
