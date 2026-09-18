<?php

return [
    // Mirrors config/console_archive.php's prune_days: this table holds flagged children's chat,
    // so it gets its own short-by-default retention knob rather than inheriting activity-log or
    // console-archive retention.
    'prune_days' => (int) env('APP_KEYWORD_ALERTS_PRUNE_DAYS', 90),

    // How long (seconds) a (rule, server) pair is deduplicated for after it first fires. Repeated
    // matches within the window bump the existing alert's occurrence_count instead of creating a
    // new row and sending a new notification — this is what stops one keyword repeated hundreds
    // of times (spam, a raid, a stuck plugin) from paging staff hundreds of times and training
    // them to ignore the channel. See app/Services/Alerts/KeywordAlertDeduplicator.php.
    'dedup_window_seconds' => (int) env('APP_KEYWORD_ALERTS_DEDUP_WINDOW_SECONDS', 600),

    // Soft wall-clock budget (milliseconds) for matching a single rule against a single line.
    // Regex rules are the risk here (catastrophic backtracking); this does not abort PHP's own
    // preg_match call (there is no portable way to do that without pcntl signals), it only
    // decides whether to log a slow-rule warning so an admin can find and fix/disable the
    // offending pattern. The actual backstop against runaway regexes is the lowered
    // pcre.backtrack_limit applied around every regex match — see KeywordAlertMatchingService.
    'slow_rule_warning_ms' => (int) env('APP_KEYWORD_ALERTS_SLOW_RULE_WARNING_MS', 50),

    // Bounds regex rules to prevent both huge patterns and (heuristically) catastrophic
    // backtracking; applied at both rule-save time and match time.
    'regex' => [
        'max_pattern_length' => (int) env('APP_KEYWORD_ALERTS_REGEX_MAX_LENGTH', 500),
        'backtrack_limit' => (int) env('APP_KEYWORD_ALERTS_REGEX_BACKTRACK_LIMIT', 200000),
    ],
];
