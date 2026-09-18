<?php

namespace Pterodactyl\Services\Alerts;

use Pterodactyl\Models\KeywordAlertRule;
use Illuminate\Support\Facades\Log;

/**
 * Matches a single captured console/chat line (app/Services/ConsoleArchive/CapturedConsoleLine.php)
 * against every enabled KeywordAlertRule. Invoked synchronously, once per line, from
 * app/Listeners/Alerts/ScanConsoleLinesForKeywordAlerts.php, which itself runs inline on the
 * console-archive ingestion daemon's own process (see ConsoleLinesCaptured's docblock) — so
 * `match()` must be fast and must never throw. Every failure mode below is handled by skipping
 * that one rule and logging, not by propagating an exception.
 *
 * ---------------------------------------------------------------------------------------------
 * Matching semantics — what this catches and what it deliberately does not
 * ---------------------------------------------------------------------------------------------
 *
 * Three match types, chosen per-rule by an admin:
 *
 *  - `word` (the default): the phrase must appear as a whole word (\b...\b) in the line, after
 *    case-folding (unless the rule is case-sensitive) and a small leetspeak canonicalisation pass
 *    (see CANONICALIZE_MAP below: @ -> a, 4 -> a, 3 -> e, 1/! -> i, 0 -> o, 5/$ -> s, 7 -> t,
 *    applied to both the line and the stored phrase before comparing). This is the Scunthorpe
 *    guard: a rule for "ass" does not fire on "assassin" or "class" because those aren't whole
 *    words, but a rule for "idiot" still fires on "1d10t" or "1D!0T" because those canonicalise
 *    to the same letters as "idiot".
 *
 *  - `substring`: the phrase may appear anywhere in the line, still through the same
 *    canonicalisation pass. Admins opt into this explicitly per-rule (it is not the default)
 *    because it is the Scunthorpe-prone mode — "assassin" *will* match a substring rule for "ass".
 *    Use it only for strings that are never legitimately part of a longer word, e.g. operational
 *    phrases like "Can't keep up" or a stack-trace marker.
 *
 *  - `regex`: the stored pattern is run as-is (admin supplies delimiters/flags, e.g. "/foo+/i")
 *    against the RAW line — canonicalisation is not applied, because an admin writing a regex has
 *    already opted into exact control over what they match and silent rewriting would make their
 *    pattern behave unpredictably. See "Regex safety" below for how this is bounded.
 *
 * What this does NOT catch, honestly:
 *  - Leetspeak coverage is a small fixed substitution table, not a general obfuscation solver. It
 *    does not catch spacing/punctuation insertion ("b u l l y", "b.u.l.l.y"), zero-width
 *    characters, homoglyphs (Cyrillic/Greek look-alike letters), reversed text, or synonyms/slang
 *    not already in a rule's phrase. A determined child *will* evade word/substring rules this
 *    way; regex rules can cover some of these patterns explicitly (e.g. `/b[\s._-]*u[\s._-]*l/i`)
 *    at the cost of being harder to author correctly and easier to get catastrophically wrong.
 *  - Multi-line context is not considered: each line is matched independently, so a message split
 *    across two console lines, or a slur assembled by one player's message continuing another's,
 *    is not detected.
 *  - This is a detection *aid*, not a moderation-decision engine — see KeywordAlert's docblock.
 *
 * ---------------------------------------------------------------------------------------------
 * Regex safety (ReDoS)
 * ---------------------------------------------------------------------------------------------
 * A user-supplied regex run against every console line on every server is a real
 * catastrophic-backtracking risk. This is bounded, not eliminated — PHP has no portable way to
 * hard-kill a single preg_match() call without pcntl signals, which are not available in every
 * deployment (e.g. under most SAPIs). Mitigations actually in place:
 *  - `pcre.backtrack_limit` is lowered (config('keyword_alerts.regex.backtrack_limit'), default
 *    200,000) around every regex match, restored immediately after. Once a pathological pattern
 *    exceeds it, PCRE aborts that one match and returns false with PREG_BACKTRACK_LIMIT_ERROR
 *    instead of hanging — this is the actual backstop.
 *  - Pattern length is capped (config('keyword_alerts.regex.max_pattern_length')) at rule-save
 *    time (see KeywordAlertRuleFormRequest), which also bounds worst-case backtracking somewhat.
 *  - A rule whose regex errors (bad syntax, backtrack limit hit, PCRE JIT stack overflow) is
 *    skipped for that line and logged once via Log::warning — it does not throw, and does not
 *    stop the remaining rules or lines in the batch from being scanned.
 *  - Wall-clock time per rule is still measured (not enforced) and a slow-rule warning is logged
 *    if it exceeds config('keyword_alerts.slow_rule_warning_ms'), so an admin can find and
 *    fix/disable an expensive pattern before it becomes a real problem under load.
 * This does NOT fully eliminate ReDoS risk for a truly adversarial admin-authored pattern under a
 * PHP build where pcre.backtrack_limit is ignored or set very high; it is a practical bound for
 * the trusted-admin threat model this feature operates under (only root admins can create rules).
 */
class KeywordAlertMatchingService
{
    /**
     * Leetspeak canonicalisation table. Deliberately small and conservative: every mapped
     * character is unambiguous enough that false positives from the substitution itself stay
     * rare (e.g. we do NOT map "5" -> "s" and "2" -> "z" both aggressively into every rule, we
     * keep this to the handful of substitutions kids actually use for filter evasion).
     */
    private const CANONICALIZE_MAP = [
        '@' => 'a',
        '4' => 'a',
        '3' => 'e',
        '1' => 'i',
        '!' => 'i',
        '0' => 'o',
        '5' => 's',
        '$' => 's',
        '7' => 't',
    ];

    /**
     * @return KeywordAlertMatch[]
     */
    public function match(string $line): array
    {
        $matches = [];

        foreach (KeywordAlertRule::enabledCached() as $rule) {
            $matchedText = $this->safelyMatchRule($rule, $line);

            if ($matchedText !== null) {
                $matches[] = new KeywordAlertMatch($rule, $matchedText);
            }
        }

        return $matches;
    }

    /**
     * Matches a single rule (not necessarily persisted/loaded from the DB) against a single
     * line, with the same never-throw/slow-rule-warning guarantees as match(). Exposed as its
     * own public method specifically so the matching semantics can be unit tested against
     * synthetic, in-memory KeywordAlertRule instances with zero database access.
     */
    public function safelyMatchRule(KeywordAlertRule $rule, string $line): ?string
    {
        try {
            $started = microtime(true);
            $matchedText = $this->matchRule($rule, $line);
            $this->warnIfSlow($rule, $started);

            return $matchedText;
        } catch (\Throwable $exception) {
            // Matching must never throw out of here — a bad rule degrades to "skipped", not
            // "console-archive ingestion stalls for every server". See class docblock.
            Log::warning('keyword_alerts: rule failed to evaluate, skipping.', [
                'rule_id' => $rule->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function matchRule(KeywordAlertRule $rule, string $line): ?string
    {
        return match ($rule->match_type) {
            KeywordAlertRule::MATCH_REGEX => $this->matchRegex($rule, $line),
            KeywordAlertRule::MATCH_SUBSTRING => $this->matchLiteral($rule, $line, wordBoundary: false),
            default => $this->matchLiteral($rule, $line, wordBoundary: true),
        };
    }

    private function matchLiteral(KeywordAlertRule $rule, string $line, bool $wordBoundary): ?string
    {
        $phrase = trim($rule->phrase);
        if ($phrase === '') {
            return null;
        }

        $normalizedLine = $this->canonicalize($line, (bool) $rule->case_sensitive);
        $normalizedPhrase = $this->canonicalize($phrase, (bool) $rule->case_sensitive);

        if ($wordBoundary) {
            $pattern = '/\b' . preg_quote($normalizedPhrase, '/') . '\b/u';
            if (@preg_match($pattern, $normalizedLine, $found) === 1) {
                return $found[0];
            }

            return null;
        }

        if (str_contains($normalizedLine, $normalizedPhrase)) {
            return $phrase;
        }

        return null;
    }

    private function matchRegex(KeywordAlertRule $rule, string $line): ?string
    {
        $pattern = trim($rule->phrase);
        if ($pattern === '') {
            return null;
        }

        $previousLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', (string) config('keyword_alerts.regex.backtrack_limit', 200000));

        try {
            $result = @preg_match($pattern, $line, $found);

            if ($result === false || preg_last_error() !== PREG_NO_ERROR) {
                Log::warning('keyword_alerts: regex rule errored (bad pattern, backtrack limit, or JIT stack overflow), skipping.', [
                    'rule_id' => $rule->id,
                    'pcre_error' => preg_last_error_msg(),
                ]);

                return null;
            }

            return $result === 1 ? ($found[0] ?? $pattern) : null;
        } finally {
            ini_set('pcre.backtrack_limit', $previousLimit === false ? '1000000' : $previousLimit);
        }
    }

    private function canonicalize(string $text, bool $caseSensitive): string
    {
        if (!$caseSensitive) {
            $text = mb_strtolower($text);
        }

        return strtr($text, self::CANONICALIZE_MAP);
    }

    private function warnIfSlow(KeywordAlertRule $rule, float $startedAt): void
    {
        $elapsedMs = (microtime(true) - $startedAt) * 1000;
        $threshold = (int) config('keyword_alerts.slow_rule_warning_ms', 50);

        if ($elapsedMs > $threshold) {
            Log::warning('keyword_alerts: rule took longer than expected to evaluate.', [
                'rule_id' => $rule->id,
                'elapsed_ms' => round($elapsedMs, 2),
                'threshold_ms' => $threshold,
            ]);
        }
    }
}
