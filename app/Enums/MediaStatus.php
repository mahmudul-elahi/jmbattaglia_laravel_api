<?php

namespace App\Enums;

enum MediaStatus: string
{
    case Pending = 'PENDING';
    case Ready = 'READY';
    case Deleted = 'DELETED';
}
