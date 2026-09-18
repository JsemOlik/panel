<?php

namespace Pterodactyl\Services\Alerts;

use Pterodactyl\Models\KeywordAlertRule;

/**
 * One rule matching one captured console/chat line. Immutable value object returned by
 * KeywordAlertMatchingService — kept separate from the eventual KeywordAlert Eloquent model so
 * the matcher can be unit tested with zero database access.
 */
final class KeywordAlertMatch
{
    public function __construct(
        public readonly KeywordAlertRule $rule,
        public readonly string $matchedText,
    ) {
    }
}
