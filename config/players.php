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
];
