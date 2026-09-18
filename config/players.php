<?php

return [
    // Days an offline player stays on the roster before being dropped from it entirely. The list
    // is meant to answer "who has been on this server lately", so a player nobody has seen for a
    // week is noise; keeping them forever turns a supervision tool into an ever-growing list of
    // every child who ever joined.
    //
    // This prunes the ROSTER only. Recorded join/leave history in server_player_sessions is
    // deliberately left alone: it is the safeguarding record, and losing it because a player
    // stopped visiting is exactly backwards. Its own retention belongs with the console archive's.
    'prune_days' => env('APP_PLAYERS_PRUNE_DAYS', 7),

    // Days join/leave history in server_player_sessions is kept before being pruned. Deliberately
    // its own key, not tied to prune_days above: the roster answers "who plays here right now" and
    // can shed a name the moment it stops being useful, but this table is the safeguarding record
    // an operator pulls up *after* something has already happened — an incident is often reported
    // days or weeks later, by which point a window as short as the roster's would have already
    // destroyed the evidence being asked for. Mirrors config/console_archive.php's prune_days in
    // shape and default: the two logs describe the same incidents from different angles (raw
    // console/chat lines vs. derived presence events) and should not go stale at different times.
    'session_prune_days' => env('APP_PLAYERS_SESSION_PRUNE_DAYS', 90),
];
