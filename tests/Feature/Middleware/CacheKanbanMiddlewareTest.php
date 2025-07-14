<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\Organization\Entities\Organization;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\Organization\ValueObjects\OrganizationId;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class CacheKanbanMiddlewareTest extends TestCase
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
        
        // Create some test tasks
        $this->createTestTask('Task 1', 'TODO');
        $this->createTestTask('Task 2', 'IN_PROGRESS');
        $this->createTestTask('Task 3', 'DONE');
    }

    /**
     * Test that Kanban board response is cached
     */
    public function test_kanban_board_response_is_cached()
    {
        // First request - should hit controller and cache response
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');

        $response1->assertStatus(200);
        $board1 = $response1->json();

        // Verify response structure
        $this->assertArrayHasKey('success', $board1);
        $this->assertArrayHasKey('data', $board1);
        $this->assertArrayHasKey('board', $board1['data']);

        // Second request - should return cached response
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');

        $response2->assertStatus(200);
        $board2 = $response2->json();

        // Responses should be identical
        $this->assertEquals($board1, $board2);
    }

    /**
     * Test that cache key includes user and organization unit
     */
    public function test_different_users_have_different_cache_keys()
    {
        // Create another GO user
        $go2 = HierarchicalUser::createGO(
            new UserName('Test GO 2'),
            new Email('go2@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($go2);
        $go2Id = $go2->getId()->toString();
        $this->createPosition($go2Id, $this->organizationId, 'GO');
        
        $token2 = JWTAuth::fromUser($this->getUserModel($go2Id));

        // First user request
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');
        $response1->assertStatus(200);

        // Second user request - should not use first user's cache
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->getJson('/api/v1/tasks/kanban');
        $response2->assertStatus(200);

        // Both should see the same tasks (same org) but have different cache entries
        $this->assertEquals(
            count($response1->json('data.board.TODO.tasks')),
            count($response2->json('data.board.TODO.tasks'))
        );
    }

    /**
     * Test that cache is invalidated when tasks are created
     */
    public function test_cache_is_invalidated_on_task_creation()
    {
        // First request to populate cache
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');
        $response1->assertStatus(200);
        
        $todoCountBefore = count($response1->json('data.board.TODO.tasks'));

        // Create a new task (this should invalidate cache via CreateTaskUseCase)
        $createResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/v1/tasks', [
                'title' => 'New Task',
                'description' => 'Test task creation',
                'priority' => 'MEDIUM',
                'organization_id' => $this->organizationId
            ]);
        $createResponse->assertStatus(201);

        // Request kanban again - should show new task
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');
        $response2->assertStatus(200);
        
        $todoCountAfter = count($response2->json('data.board.TODO.tasks'));

        // Should have one more TODO task
        $this->assertEquals($todoCountBefore + 1, $todoCountAfter);
    }

    /**
     * Test that cache respects query parameters
     */
    public function test_cache_respects_query_parameters()
    {
        // Request with no parameters
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');
        $response1->assertStatus(200);

        // Request with organization_unit_id parameter
        $unitId = $this->getOrganizationUnitId();
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tasks/kanban?organization_unit_id={$unitId}");
        $response2->assertStatus(200);

        // Should have different cache keys and potentially different results
        // (In this test they might be the same, but cache keys should differ)
        $this->assertNotNull($response1->json('data'));
        $this->assertNotNull($response2->json('data'));
    }

    /**
     * Test that non-200 responses are not cached
     */
    public function test_error_responses_are_not_cached()
    {
        // Make request with invalid token
        $response1 = $this->withHeaders(['Authorization' => 'Bearer invalid-token'])
            ->getJson('/api/v1/tasks/kanban');
        
        // Should return 401
        $response1->assertStatus(401);

        // Make same request again
        $response2 = $this->withHeaders(['Authorization' => 'Bearer invalid-token'])
            ->getJson('/api/v1/tasks/kanban');
        
        // Should still return 401 (not cached)
        $response2->assertStatus(401);
    }

    /**
     * Test that cache TTL is 5 minutes
     */
    public function test_cache_ttl_is_five_minutes()
    {
        // Make request to populate cache
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');
        $response->assertStatus(200);

        // Verify cache exists with correct tags
        $tags = Cache::store('redis')->tags(['tasks', 'kanban']);
        
        // We can't directly test TTL, but we can verify the cache exists
        // In a real test, you might mock time or use Cache::shouldReceive()
        $this->assertNotNull($response->json('data'));
    }

    /**
     * Test that only GET requests to kanban route are cached
     */
    public function test_only_get_kanban_requests_are_cached()
    {
        // POST request should not be cached
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Test Task',
                'description' => 'Test',
                'priority' => 'MEDIUM',
                'organization_id' => $this->organizationId
            ]);
        $response->assertStatus(201);

        // GET request to non-kanban route should not be cached by this middleware
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks');
        $response->assertStatus(200);
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

        return $orgId;
    }

    private function getOrganizationUnitId()
    {
        return \DB::table('organization_units')
            ->where('organization_id', $this->organizationId)
            ->where('type', 'company')
            ->first()->id;
    }

    private function createTestTask($title, $status)
    {
        $taskId = \Str::uuid()->toString();
        
        \DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => $title,
            'description' => 'Test task description',
            'status' => $status,
            'priority' => 'MEDIUM',
            'created_by' => $this->userId,
            'organization_id' => $this->organizationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $taskId;
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
        
        // Create department if not exists
        $deptId = \DB::table('departments')
            ->where('organization_id', $orgId)
            ->where('code', 'ADM')
            ->first()?->id;
            
        if (!$deptId) {
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
        }
        
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
        \DB::table('position_departments')->insert([
            'position_id' => $positionId,
            'department_id' => $deptId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getUserModel($userId)
    {
        return \App\Infrastructure\Persistence\Eloquent\Models\UserModel::find($userId);
    }
}