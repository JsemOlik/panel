<?php

namespace Pterodactyl\Tests\Unit\Models;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Tests\TestCase;

class SubuserTest extends TestCase
{
    public function testGrantWithNoExpiryIsNeverExpired(): void
    {
        $subuser = new Subuser(['expires_at' => null]);

        $this->assertFalse($subuser->isExpired());
    }

    public function testGrantWithFutureExpiryIsNotExpired(): void
    {
        $subuser = new Subuser(['expires_at' => CarbonImmutable::now()->addMinute()]);

        $this->assertFalse($subuser->isExpired());
    }

    public function testGrantWithPastExpiryIsExpired(): void
    {
        $subuser = new Subuser(['expires_at' => CarbonImmutable::now()->subMinute()]);

        $this->assertTrue($subuser->isExpired());
    }

    /**
     * A grant that expired one second ago must fail closed even when the
     * check happens on the very next tick — this is the case that matters in
     * production, as opposed to a contrived exact-equality boundary.
     */
    public function testGrantThatJustExpiredIsExpired(): void
    {
        $subuser = new Subuser(['expires_at' => CarbonImmutable::now()->subSecond()]);

        $this->assertTrue($subuser->isExpired());
    }
}
