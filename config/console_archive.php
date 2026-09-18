<?php

return [
    // The number of days elapsed before old console archive entries are deleted. Mirrors
    // config/activity.php's prune_days in shape and default, but is deliberately its own knob:
    // this table holds captured children's chat, which should not silently inherit whatever
    // value an operator has set for admin activity-log retention.
    'prune_days' => env('APP_CONSOLE_ARCHIVE_PRUNE_DAYS', 90),

    // Whether the console archive ingestion daemon (`console-archive:consume`) is allowed to run
    // at all. A hard kill switch independent of whether the process is scheduled under a
    // supervisor, for operators who need to disable capture immediately (e.g. a legal hold or an
    // incident) without touching deployment config.
    'enabled' => env('APP_CONSOLE_ARCHIVE_ENABLED', true),

    'ingest' => [
        // Batched insert tuning: a line is flushed to the database when either the buffer
        // reaches `batch_size` lines or `batch_interval_ms` milliseconds have elapsed since the
        // oldest buffered line, whichever comes first. This bounds both worst-case memory use and
        // worst-case latency between "a child typed this" and "it is queryable".
        'batch_size' => (int) env('APP_CONSOLE_ARCHIVE_BATCH_SIZE', 200),
        'batch_interval_ms' => (int) env('APP_CONSOLE_ARCHIVE_BATCH_INTERVAL_MS', 1500),

        // Longest single line stored, in characters (after ANSI stripping). Plugin stack traces
        // can be enormous; without a cap a single bad line can bloat a row and degrade the
        // fulltext index. Truncated lines are suffixed with a marker so it's visible in search
        // results that content was cut.
        'max_line_length' => (int) env('APP_CONSOLE_ARCHIVE_MAX_LINE_LENGTH', 4096),

        // Wings websocket JWTs minted for the daemon itself (via NodeJWTService, no end-user
        // attached) live this long before the daemon proactively re-authenticates on the
        // "token expiring" event, exactly like the browser client does.
        'token_ttl_minutes' => (int) env('APP_CONSOLE_ARCHIVE_TOKEN_TTL_MINUTES', 55),

        // Reconnect backoff (seconds) applied after a socket closes unexpectedly (Wings restart,
        // network blip, node reboot). Doubles each consecutive failed attempt up to the max, then
        // holds there until a connection succeeds, at which point the count resets.
        'reconnect_base_seconds' => (int) env('APP_CONSOLE_ARCHIVE_RECONNECT_BASE_SECONDS', 1),
        'reconnect_max_seconds' => (int) env('APP_CONSOLE_ARCHIVE_RECONNECT_MAX_SECONDS', 60),

        // How often (seconds) the daemon re-queries the server list to pick up newly created
        // servers or drop deleted/suspended ones, on top of reacting to it at boot.
        'server_reconcile_seconds' => (int) env('APP_CONSOLE_ARCHIVE_RECONCILE_SECONDS', 120),
    ],
];
