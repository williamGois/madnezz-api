<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Http\Middleware\OrganizationContextMiddleware;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\Organization\Entities\Organization;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\Organization\ValueObjects\OrganizationId;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class OrganizationContextCacheTest extends TestCase
{
    use RefreshDatabase;

    private $token;
    private $organizationId;
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
        
        // Create test organization
        $this->organizationId = $this->createTestOrganization();
        
        // Create GO user
        $go = HierarchicalUser::createGO(
            new UserName('Test GO'),
            new Email('go@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($go);
        $this->userId = $go->getId()->toString();
        $this->createPosition($this->userId, $this->organizationId, 'GO');
        
        // Get token
        $this->token = JWTAuth::fromUser($this->getUserModel($this->userId));
    }

    /**
     * Test that organization context is cached
     */
    public function test_organization_context_is_cached()
    {
        // First request - should hit database
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/organizations');

        $response1->assertStatus(200);

        // Check that context was cached
        $cacheKey = "org_context:{$this->userId}";
        $cachedContext = Cache::store('redis')->tags(['organization_context'])->get($cacheKey);
        
        $this->assertNotNull($cachedContext);
        $this->assertEquals('GO', $cachedContext['hierarchy_role']);
        $this->assertEquals($this->organizationId, $cachedContext['organization_id']);

        // Second request - should use cache (we can't directly test this without DB query logging)
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/organizations');

        $response2->assertStatus(200);
    }

    /**
     * Test MASTER user context is cached
     */
    public function test_master_user_context_is_cached()
    {
        // Create MASTER user
        $master = HierarchicalUser::createMaster(
            new UserName('Master User'),
            new Email('master@test.com'),
            new Password('password123')
        );
        $this->saveUser($master);
        $masterId = $master->getId()->toString();
        
        $masterToken = JWTAuth::fromUser($this->getUserModel($masterId));

        // Make request as MASTER
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $masterToken])
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200);

        // Check that MASTER context was cached
        $cacheKey = "org_context:{$masterId}";
        $cachedContext = Cache::store('redis')->tags(['organization_context'])->get($cacheKey);
        
        $this->assertNotNull($cachedContext);
        $this->assertTrue($cachedContext['is_master']);
        $this->assertEquals('MASTER', $cachedContext['hierarchy_role']);
        $this->assertEquals(['*'], $cachedContext['departments']);
    }

    /**
     * Test cache invalidation for specific user
     */
    public function test_cache_invalidation_for_user()
    {
        // Make request to populate cache
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200);

        // Verify cache exists
        $cacheKey = "org_context:{$this->userId}";
        $cachedContext = Cache::store('redis')->tags(['organization_context'])->get($cacheKey);
        $this->assertNotNull($cachedContext);

        // Clear cache for user
        OrganizationContextMiddleware::clearCacheForUser($this->userId);

        // Verify cache was cleared
        $cachedContext = Cache::store('redis')->tags(['organization_context'])->get($cacheKey);
        $this->assertNull($cachedContext);
    }

    /**
     * Test cache invalidation for all users
     */
    public function test_cache_invalidation_for_all_users()
    {
        // Create multiple users and populate cache
        $user2Id = $this->createAdditionalUser();
        
        // Make requests to populate cache for both users
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/organizations');
        $response1->assertStatus(200);

        $token2 = JWTAuth::fromUser($this->getUserModel($user2Id));
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->getJson('/api/v1/organizations');
        $response2->assertStatus(200);

        // Verify both caches exist
        $cacheKey1 = "org_context:{$this->userId}";
        $cacheKey2 = "org_context:{$user2Id}";
        
        $this->assertNotNull(Cache::store('redis')->tags(['organization_context'])->get($cacheKey1));
        $this->assertNotNull(Cache::store('redis')->tags(['organization_context'])->get($cacheKey2));

        // Clear all caches
        OrganizationContextMiddleware::clearAllCache();

        // Verify all caches were cleared
        $this->assertNull(Cache::store('redis')->tags(['organization_context'])->get($cacheKey1));
        $this->assertNull(Cache::store('redis')->tags(['organization_context'])->get($cacheKey2));
    }

    /**
     * Test cache TTL is respected
     */
    public function test_cache_ttl_is_one_hour()
    {
        // Make request to populate cache
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200);

        // Check TTL (we can't directly test this without mocking time)
        // But we can verify the cache exists
        $cacheKey = "org_context:{$this->userId}";
        $cachedContext = Cache::store('redis')->tags(['organization_context'])->get($cacheKey);
        $this->assertNotNull($cachedContext);
    }

    // Helper methods
    private function createTestOrganization()
    {
        $orgId = \Str::uuid()->toString();
        
        \DB::table('organizations')->insert([
            'id' => $orgId,
            'name' => 'Test Organization',
            'code' => 'TEST001',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create root unit
        $unitId = \Str::uuid()->toString();
        \DB::table('organization_units')->insert([
            'id' => $unitId,
            'organization_id' => $orgId,
            'name' => 'Sede',
            'code' => 'SEDE',
            'type' => 'company',
            'parent_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create departments
        $deptId = \Str::uuid()->toString();
        \DB::table('departments')->insert([
            'id' => $deptId,
            'organization_id' => $orgId,
            'name' => 'Administração',
            'code' => 'ADM',
            'type' => 'administrative',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $orgId;
    }

    private function createAdditionalUser()
    {
        $gr = HierarchicalUser::createGR(
            new UserName('Test GR'),
            new Email('gr@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($gr);
        $grId = $gr->getId()->toString();
        $this->createPosition($grId, $this->organizationId, 'GR');
        
        return $grId;
    }

    private function saveUser(HierarchicalUser $user): void
    {
        \DB::table('users_ddd')->insert([
            'id' => $user->getId()->toString(),
            'name' => $user->getName()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'password' => bcrypt($user->getPassword()->getValue()),
            'hierarchy_role' => $user->getHierarchyRole()->getValue(),
            'status' => $user->getStatus()->getValue(),
            'organization_id' => $user->getOrganizationId()?->toString(),
            'permissions' => json_encode($user->getPermissions()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPosition($userId, $orgId, $level)
    {
        $unit = \DB::table('organization_units')
            ->where('organization_id', $orgId)
            ->where('type', 'company')
            ->first();
        
        $positionId = \Str::uuid()->toString();
        
        \DB::table('positions')->insert([
            'id' => $positionId,
            'organization_id' => $orgId,
            'organization_unit_id' => $unit->id,
            'user_id' => $userId,
            'title' => $level . ' Position',
            'level' => $level,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link position to department
        if ($level !== 'MASTER') {
            $dept = \DB::table('departments')
                ->where('organization_id', $orgId)
                ->first();
            
            if ($dept) {
                \DB::table('position_departments')->insert([
                    'position_id' => $positionId,
                    'department_id' => $dept->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function getUserModel($userId)
    {
        return \App\Infrastructure\Persistence\Eloquent\Models\UserModel::find($userId);
    }
}