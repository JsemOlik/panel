<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\Subuser;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Policies\ServerPolicy;
use Pterodactyl\Services\Servers\GetUserPermissionsService;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Proves that a time-boxed subuser grant is actually enforced everywhere a
 * "this user + this server" decision is made, not just that the expires_at
 * column round-trips through the API. Every test in this class creates a
 * subuser whose grant has already lapsed and then asserts that access is
 * denied through a *different* code path than the others, so that a fix
 * applied to only one choke point cannot make the whole suite pass.
 *
 * The choke points covered:
 *   1. Http\Middleware\Api\Client\Server\AuthenticateServerAccess — the
 *      outermost gate every server-scoped client API route passes through
 *      before any controller/policy code runs at all.
 *   2. Policies\ServerPolicy::checkPermission() — the Gate-backed check
 *      behind every ClientApiRequest::authorize() call.
 *   3. Services\Servers\GetUserPermissionsService::handle() — the source of
 *      truth for the websocket JWT claims and for what a subuser may grant
 *      another subuser.
 *   4. Models\User::accessibleServers() — what shows up on the client
 *      dashboard ("my servers").
 */
class SubuserExpiryEnforcementTest extends ClientApiIntegrationTestCase
{
    private function createExpiredSubuser(User $user, \Pterodactyl\Models\Server $server, array $permissions = [Permission::ACTION_WEBSOCKET_CONNECT]): Subuser
    {
        return Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'permissions' => $permissions,
            // Deliberately bypassing the StoreSubuserRequest "after:now" validation
            // rule by writing directly to the model, exactly as the real world
            // case looks once time has simply passed for a grant that WAS valid
            // when it was created.
            'expires_at' => CarbonImmutable::now()->subHour(),
        ]);
    }

    private function createActiveSubuser(User $user, \Pterodactyl\Models\Server $server, array $permissions = [Permission::ACTION_WEBSOCKET_CONNECT], ?CarbonImmutable $expiresAt = null): Subuser
    {
        return Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'permissions' => $permissions,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Choke point 1: the outermost gate. Even an endpoint that does not run any
     * further permission check at all (GetServerRequest::authorize() always
     * returns true, trusting this middleware) must 404 for an expired subuser.
     */
    public function testExpiredSubuserCannotViewServerAtAll(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $this->createExpiredSubuser($user, $server);

        $this->actingAs($user)
            ->getJson("/api/client/servers/{$server->uuid}")
            ->assertNotFound();
    }

    /**
     * Control group for the above: a subuser with a future expiry (or none at
     * all) must still be able to view the server. If this fails, the previous
     * test is meaningless — it could be failing for an unrelated reason.
     */
    public function testActiveSubuserCanStillViewServer(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $this->createActiveSubuser($user, $server, [Permission::ACTION_WEBSOCKET_CONNECT], CarbonImmutable::now()->addDay());

        $this->actingAs($user)
            ->getJson("/api/client/servers/{$server->uuid}")
            ->assertOk();

        // And one with no expiry at all (a permanent grant) is unaffected by any
        // of this work.
        $server2 = $this->createServerModel();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->createActiveSubuser($user2, $server2, [Permission::ACTION_WEBSOCKET_CONNECT], null);

        $this->actingAs($user2)
            ->getJson("/api/client/servers/{$server2->uuid}")
            ->assertOk();
    }

    /**
     * Choke point 1, second route: the websocket endpoint mints a signed JWT
     * that Wings trusts unconditionally. An expired subuser must never reach
     * the point where a token is issued at all.
     */
    public function testExpiredSubuserCannotObtainWebsocketToken(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $this->createExpiredSubuser($user, $server, [Permission::ACTION_WEBSOCKET_CONNECT]);

        $this->actingAs($user)
            ->getJson("/api/client/servers/{$server->uuid}/websocket")
            ->assertNotFound();
    }

    /**
     * Choke point 2: ServerPolicy::checkPermission(), exercised directly
     * (bypassing the middleware entirely) so this test fails independently of
     * whether AuthenticateServerAccess is doing its job. This is the test that
     * proves the actual `in_array($permission, ...)` gate fails closed.
     */
    public function testServerPolicyDeniesExpiredSubuserDirectly(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $subuser = $this->createExpiredSubuser($user, $server, [Permission::ACTION_WEBSOCKET_CONNECT, Permission::ACTION_CONTROL_CONSOLE]);

        // Load the relation exactly the way the policy expects to find it,
        // without going through any HTTP middleware.
        $server->setRelation('subusers', collect([$subuser]));

        $policy = new ServerPolicy();

        $this->assertFalse(
            $policy->before($user, Permission::ACTION_CONTROL_CONSOLE, $server),
            'An expired subuser must be denied a permission it was explicitly granted before expiry.'
        );
        $this->assertFalse(
            $policy->before($user, Permission::ACTION_WEBSOCKET_CONNECT, $server),
            'An expired subuser must be denied websocket.connect, which every subuser is normally granted by default.'
        );
    }

    /**
     * Same test shape, but for a still-active grant — proves the assertions
     * above are actually about expiry, not about the permission list itself.
     */
    public function testServerPolicyAllowsActiveSubuserDirectly(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $subuser = $this->createActiveSubuser($user, $server, [Permission::ACTION_CONTROL_CONSOLE], CarbonImmutable::now()->addDay());

        $server->setRelation('subusers', collect([$subuser]));

        $policy = new ServerPolicy();

        $this->assertTrue($policy->before($user, Permission::ACTION_CONTROL_CONSOLE, $server));
    }

    /**
     * Choke point 3: GetUserPermissionsService must independently fail closed,
     * since its return value is trusted directly as JWT claims sent to Wings
     * and is also used to cap what a subuser can grant another subuser.
     */
    public function testGetUserPermissionsServiceReturnsEmptyForExpiredSubuser(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $this->createExpiredSubuser($user, $server, [Permission::ACTION_WEBSOCKET_CONNECT, Permission::ACTION_CONTROL_CONSOLE]);

        /** @var GetUserPermissionsService $service */
        $service = $this->app->make(GetUserPermissionsService::class);

        $this->assertSame([], $service->handle($server, $user));
    }

    /**
     * Control group for choke point 3.
     */
    public function testGetUserPermissionsServiceReturnsPermissionsForActiveSubuser(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $this->createActiveSubuser($user, $server, [Permission::ACTION_WEBSOCKET_CONNECT, Permission::ACTION_CONTROL_CONSOLE], CarbonImmutable::now()->addDay());

        /** @var GetUserPermissionsService $service */
        $service = $this->app->make(GetUserPermissionsService::class);

        $this->assertSame([Permission::ACTION_WEBSOCKET_CONNECT, Permission::ACTION_CONTROL_CONSOLE], $service->handle($server, $user));
    }

    /**
     * Choke point 4: the client dashboard's "my servers" list. An expired
     * grant must not leave a server lingering in the list forever.
     */
    public function testAccessibleServersExcludesExpiredGrant(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $this->createExpiredSubuser($user, $server);

        $ids = $user->accessibleServers()->pluck('servers.id')->all();

        $this->assertNotContains($server->id, $ids);
    }

    public function testAccessibleServersIncludesActiveGrant(): void
    {
        $server = $this->createServerModel();
        /** @var User $user */
        $user = User::factory()->create();
        $this->createActiveSubuser($user, $server, [Permission::ACTION_WEBSOCKET_CONNECT], CarbonImmutable::now()->addDay());

        $ids = $user->accessibleServers()->pluck('servers.id')->all();

        $this->assertContains($server->id, $ids);
    }

    /**
     * The dashboard listing endpoint itself, end to end, on top of the model
     * query tested above.
     */
    public function testDashboardServerListingHidesServerForExpiredSubuser(): void
    {
        $server = $this->createServerModel(['name' => 'expired-access-server']);
        /** @var User $user */
        $user = User::factory()->create();
        $this->createExpiredSubuser($user, $server);

        $json = $this->actingAs($user)->getJson('/api/client')->assertOk()->json();

        $uuids = array_map(fn ($row) => $row['attributes']['uuid'], $json['data']);

        $this->assertNotContains($server->uuid, $uuids);
    }

    /**
     * Confirms extension still works: the *owner* editing an already-expired
     * subuser is not blocked by any of the above (the owner path bypasses
     * ServerPolicy/AuthenticateServerAccess subuser checks entirely), and the
     * row survives expiry rather than being deleted, so it can be revived.
     */
    public function testOwnerCanExtendAnExpiredSubuser(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        /** @var User $subuserAccount */
        $subuserAccount = User::factory()->create();
        $subuser = $this->createExpiredSubuser($subuserAccount, $server, [Permission::ACTION_WEBSOCKET_CONNECT]);

        $newExpiry = CarbonImmutable::now()->addDays(3);

        $this->actingAs($owner)
            ->postJson("/api/client/servers/{$server->uuid}/users/{$subuserAccount->uuid}", [
                'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT],
                'expires_at' => $newExpiry->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('attributes.expires_at', $newExpiry->toIso8601String())
            ->assertJsonPath('attributes.is_expired', false);

        $subuser->refresh();
        $this->assertFalse($subuser->isExpired());

        // And now that it has been extended, the previously-expired subuser can
        // access the server again.
        $this->actingAs($subuserAccount)
            ->getJson("/api/client/servers/{$server->uuid}")
            ->assertOk();
    }

    /**
     * Validation: the API refuses to create a grant that is already expired at
     * creation time — that would be a pointless, confusing state to allow.
     */
    public function testCreatingSubuserWithPastExpiryIsRejected(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        /** @var User $target */
        $target = User::factory()->create();

        $this->actingAs($owner)
            ->postJson("/api/client/servers/{$server->uuid}/users", [
                'email' => $target->email,
                'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT],
                'expires_at' => CarbonImmutable::now()->subHour()->toIso8601String(),
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Contract test (paired with the frontend parsers, in the style of
     * AreaFrontendContractTest): resources/scripts/api/server/users/getServerSubusers.ts
     * -> rawDataToServerSubuser() reads `data.attributes.expires_at` (ISO string
     * or null) and `data.attributes.is_expired` (boolean) directly off each
     * item. If the transformer key names or shapes drift from this, the
     * frontend badge silently renders nothing instead of throwing, so this is
     * pinned explicitly here rather than relied upon to blow up visibly.
     */
    public function testSubuserTransformerMatchesWhatTheFrontendParses(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        /** @var User $target */
        $target = User::factory()->create();

        $expiresAt = CarbonImmutable::now()->addDays(2);

        $created = $this->actingAs($owner)
            ->postJson("/api/client/servers/{$server->uuid}/users", [
                'email' => $target->email,
                'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT],
                'expires_at' => $expiresAt->toIso8601String(),
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('expires_at', $created['attributes'], 'getServerSubusers.ts reads attributes.expires_at.');
        $this->assertArrayHasKey('is_expired', $created['attributes'], 'getServerSubusers.ts reads attributes.is_expired.');
        $this->assertSame($expiresAt->toIso8601String(), $created['attributes']['expires_at']);
        $this->assertFalse($created['attributes']['is_expired']);

        // And the index endpoint (used to populate the users list) returns the
        // same shape for each row.
        $index = $this->actingAs($owner)
            ->getJson("/api/client/servers/{$server->uuid}/users")
            ->assertOk()
            ->json();

        $row = collect($index['data'])->firstWhere('attributes.uuid', $target->uuid);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('expires_at', $row['attributes']);
        $this->assertArrayHasKey('is_expired', $row['attributes']);

        // A subuser created with no expiry at all must serialize to `null`, not
        // an empty string or an omitted key — getServerSubusers.ts does
        // `data.attributes.expires_at ? new Date(...) : null` which only works
        // correctly against `null`/falsy, not a missing key (TypeScript would
        // still compile, but the value would be `undefined` at runtime).
        /** @var User $permanentTarget */
        $permanentTarget = User::factory()->create();
        $permanent = $this->actingAs($owner)
            ->postJson("/api/client/servers/{$server->uuid}/users", [
                'email' => $permanentTarget->email,
                'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT],
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('expires_at', $permanent['attributes']);
        $this->assertNull($permanent['attributes']['expires_at']);
        $this->assertFalse($permanent['attributes']['is_expired']);
    }
}
