<?php

return [
    // How often raw per-minute samples are kept before being pruned. The "hour" and "day"
    // history ranges are served from raw samples, so this must stay comfortably above 24h.
    'raw_retention_days' => env('PTERODACTYL_RESOURCE_HISTORY_RAW_DAYS', 3),

    // How long hourly rollups are kept. The "week" and "month" history ranges are served
    // from rollups, so this must stay comfortably above 31 days. Kept for a year by default
    // so staff can spot longer-term trends (e.g. sizing servers for next season).
    'rollup_retention_days' => env('PTERODACTYL_RESOURCE_HISTORY_ROLLUP_DAYS', 365),
];
