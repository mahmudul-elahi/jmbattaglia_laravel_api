<?php

namespace App\Support;

final class PropertyOptions
{
    public const CHOICES = [
        'clientAttendance' => ['FULL_INSPECTION', 'FINAL_WALKTHROUGH', 'NOT_ATTENDING', 'AGENT_ATTENDING'],
        'occupancy' => ['OCCUPIED', 'VACANT_UNFURNISHED', 'VACANT_STAGED', 'NEW_CONSTRUCTION'],
        'accessType' => ['OPEN', 'GATE_CODE', 'GUARD', 'INTERCOM'], 'alarmStatus' => ['NONE', 'DISARMED', 'CODE_REQUIRED', 'UNKNOWN'],
        'poolSpaType' => ['NONE', 'IN_GROUND', 'ABOVE_GROUND', 'HOT_TUB', 'COMBINATION'],
        'utilityStatus' => ['ALL_ON', 'ELECTRIC_OFF', 'WATER_OFF', 'GAS_OFF', 'MULTIPLE_OFF'],
        'sewageSystem' => ['PUBLIC', 'SEPTIC', 'ATU', 'SHARED_SEPTIC'], 'waterSystem' => ['PUBLIC', 'WELL', 'SHARED_WELL', 'CISTERN'],
    ];

    public const FOUNDATION_ALIASES = ['SLAB' => 'SLABONGRADE', 'CRAWLSPACE' => 'CRAWLSPACEPIERANDBEAM', 'PIERBEAM' => 'CRAWLSPACEPIERANDBEAM', 'FINISHEDBASEMENT' => 'FINISHEDBASEMENT', 'UNFINISHEDBASEMENT' => 'UNFINISHEDBASEMENT'];
}
