<?php

namespace App\Enums;

enum VisaStatus: string
{
    case None = 'NONE';
    case F1Opt = 'F1_OPT';
    case H1B = 'H1B';
    case J1 = 'J1';
    case GreenCard = 'GREEN_CARD';
    case Citizen = 'CITIZEN';
    case Other = 'OTHER';
}
