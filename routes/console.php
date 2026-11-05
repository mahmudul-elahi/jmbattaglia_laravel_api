<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('portal:cleanup')->daily()->withoutOverlapping();
Schedule::command('portal:dispatch-receipts')->everyMinute()->withoutOverlapping();
Schedule::command('portal:recover-stalled')->everyFiveMinutes()->withoutOverlapping();
