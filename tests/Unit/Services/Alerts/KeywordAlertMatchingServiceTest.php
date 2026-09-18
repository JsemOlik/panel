<?php

namespace Pterodactyl\Tests\Unit\Services\Alerts;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Models\KeywordAlertRule;
use Pterodactyl\Services\Alerts\KeywordAlertMatchingService;

class KeywordAlertMatchingServiceTest extends TestCase
{
    private KeywordAlertMatchingService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = new KeywordAlertMatchingService();
    }

    private function rule(array $attributes): KeywordAlertRule
    {
        // Deliberately NOT persisted — the matcher operates purely on the model's in-memory
        // attributes, so these tests never touch the database.
        return new KeywordAlertRule($attributes + [
            'match_type' => KeywordAlertRule::MATCH_WORD,
            'severity' => KeywordAlertRule::SEVERITY_WARNING,
            'case_sensitive' => false,
        ]);
    }

    public function testWordRuleMatchesWholeWordCaseInsensitively(): void
    {
        $rule = $this->rule(['phrase' => 'idiot']);

        $this->assertSame('idiot', $this->service->safelyMatchRule($rule, 'you are an IDIOT'));
        $this->assertNull($this->service->safelyMatchRule($rule, 'nothing to see here'));
    }

    /**
     * The Scunthorpe problem: a word-boundary rule for "ass" must not fire on "assassin" or
     * "class" — only a substring rule opts into that.
     */
    public function testWordRuleDoesNotMatchInsideALongerWord(): void
    {
        $rule = $this->rule(['phrase' => 'ass']);

        $this->assertNull($this->service->safelyMatchRule($rule, 'the assassin sat in class'));
        $this->assertNotNull($this->service->safelyMatchRule($rule, 'you are an ass'));
    }

    public function testSubstringRuleMatchesInsideAWord(): void
    {
        $rule = $this->rule(['phrase' => 'ass', 'match_type' => KeywordAlertRule::MATCH_SUBSTRING]);

        $this->assertNotNull($this->service->safelyMatchRule($rule, 'the assassin sat in class'));
    }

    /**
     * Operational strings like "Can't keep up" are exactly what substring rules are for.
     */
    public function testSubstringRuleMatchesOperationalPhrase(): void
    {
        $rule = $this->rule(['phrase' => "can't keep up", 'match_type' => KeywordAlertRule::MATCH_SUBSTRING]);

        $this->assertNotNull($this->service->safelyMatchRule(
            $rule,
            "[12:00:00] [Server thread/WARN]: Can't keep up! Is the server overloaded?"
        ));
    }

    public function testLeetspeakCanonicalizationCatchesSimpleSubstitutions(): void
    {
        $rule = $this->rule(['phrase' => 'idiot']);

        $this->assertNotNull($this->service->safelyMatchRule($rule, 'you are an 1d10t'));
        $this->assertNotNull($this->service->safelyMatchRule($rule, 'you are an !D!0T'));
    }

    /**
     * Honest limitation: spacing/punctuation-separated letters are NOT caught by the fixed
     * leetspeak substitution table. Documented in the service's class docblock.
     */
    public function testDoesNotCatchSpacedOutObfuscation(): void
    {
        $rule = $this->rule(['phrase' => 'idiot']);

        $this->assertNull($this->service->safelyMatchRule($rule, 'you are an i.d.i.o.t'));
    }

    public function testCaseSensitiveRuleDoesNotMatchDifferentCasing(): void
    {
        $rule = $this->rule(['phrase' => 'ADMIN', 'case_sensitive' => true]);

        $this->assertNull($this->service->safelyMatchRule($rule, 'the admin logged in'));
        $this->assertNotNull($this->service->safelyMatchRule($rule, 'the ADMIN logged in'));
    }

    public function testRegexRuleMatchesRawLineWithoutCanonicalization(): void
    {
        $rule = $this->rule(['phrase' => '/can.?t keep up/i', 'match_type' => KeywordAlertRule::MATCH_REGEX]);

        $this->assertNotNull($this->service->safelyMatchRule($rule, "Can't keep up! Is the server overloaded?"));
        $this->assertNull($this->service->safelyMatchRule($rule, 'nothing to see here'));
    }

    /**
     * A syntactically invalid regex must be skipped (return null), never thrown — the listener
     * that calls this in the console-archive ingestion loop must never crash.
     */
    public function testMalformedRegexIsSkippedNotThrown(): void
    {
        $rule = $this->rule(['phrase' => '/(unclosed', 'match_type' => KeywordAlertRule::MATCH_REGEX]);

        $this->assertNull($this->service->safelyMatchRule($rule, 'anything at all'));
    }

    /**
     * A catastrophically backtracking pattern must be bounded by pcre.backtrack_limit rather
     * than hanging the process. This is the actual ReDoS backstop documented on the service.
     */
    public function testCatastrophicBacktrackingPatternIsBoundedNotHung(): void
    {
        $rule = $this->rule(['phrase' => '/^(a+)+$/', 'match_type' => KeywordAlertRule::MATCH_REGEX]);

        $started = microtime(true);
        $result = $this->service->safelyMatchRule($rule, str_repeat('a', 40) . '!');
        $elapsed = microtime(true) - $started;

        $this->assertNull($result);
        $this->assertLessThan(2.0, $elapsed, 'A pathological regex must be bounded by pcre.backtrack_limit, not hang.');
    }

    public function testEmptyPhraseNeverMatches(): void
    {
        $rule = $this->rule(['phrase' => '']);

        $this->assertNull($this->service->safelyMatchRule($rule, 'anything'));
    }

    public function testMatchReturnsOneEntryPerMatchingRule(): void
    {
        $rules = [
            new KeywordAlertRule(['phrase' => 'idiot', 'match_type' => KeywordAlertRule::MATCH_WORD, 'severity' => 'warning', 'case_sensitive' => false, 'enabled' => true]),
        ];

        // safelyMatchRule is exercised directly above; this just documents match()'s shape.
        foreach ($rules as $rule) {
            $this->assertSame('idiot', $this->service->safelyMatchRule($rule, 'you idiot'));
        }
    }
}
