<?php

namespace Tests\Feature\Task;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Domain\Task\Entities\Task;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\Organization\Entities\Organization;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\Organization\ValueObjects\OrganizationId;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class TaskCacheTest extends TestCase
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
     * Test that filtered tasks are cached
     */
    public function test_filtered_tasks_are_cached()
    {
        // Create test tasks
        $this->createTestTask('Task 1', 'TODO');
        $this->createTestTask('Task 2', 'IN_PROGRESS');

        // First call - should hit database
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered?status=TODO');

        $response1->assertStatus(200);
        $tasks1 = $response1->json('data.tasks');

        // Second call - should hit cache
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered?status=TODO');

        $response2->assertStatus(200);
        $tasks2 = $response2->json('data.tasks');

        // Results should be identical
        $this->assertEquals($tasks1, $tasks2);

        // Verify cache tags exist
        $this->assertTrue(Cache::tags(['tasks', 'kanban'])->has($this->getCacheKey('filtered_tasks')));
    }

    /**
     * Test that kanban board is cached
     */
    public function test_kanban_board_is_cached()
    {
        // Create test tasks
        $this->createTestTask('Task 1', 'TODO');
        $this->createTestTask('Task 2', 'IN_PROGRESS');
        $this->createTestTask('Task 3', 'DONE');

        // First call - should hit database
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');

        $response1->assertStatus(200);
        $board1 = $response1->json('data.board');

        // Second call - should hit cache
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/kanban');

        $response2->assertStatus(200);
        $board2 = $response2->json('data.board');

        // Results should be identical
        $this->assertEquals($board1, $board2);

        // Verify board structure
        $this->assertArrayHasKey('TODO', $board1);
        $this->assertArrayHasKey('IN_PROGRESS', $board1);
        $this->assertArrayHasKey('DONE', $board1);
    }

    /**
     * Test that cache is invalidated on task creation
     */
    public function test_cache_invalidated_on_task_creation()
    {
        // Create initial task
        $this->createTestTask('Initial Task', 'TODO');

        // Load tasks to populate cache
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered');

        $response1->assertStatus(200);
        $initialCount = count($response1->json('data.tasks'));

        // Create new task via API
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/v1/tasks', [
                'title' => 'New Task',
                'description' => 'Test task creation',
                'priority' => 'MEDIUM',
                'organization_id' => $this->organizationId
            ]);

        $response->assertStatus(201);

        // Load tasks again - should not use cache
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered');

        $response2->assertStatus(200);
        $newCount = count($response2->json('data.tasks'));

        // Should have one more task
        $this->assertEquals($initialCount + 1, $newCount);
    }

    /**
     * Test that cache is invalidated on task update
     */
    public function test_cache_invalidated_on_task_update()
    {
        // Create task
        $taskId = $this->createTestTask('Original Title', 'TODO');

        // Load tasks to populate cache
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered');

        $response1->assertStatus(200);
        $tasks = $response1->json('data.tasks');
        $originalTask = collect($tasks)->firstWhere('id', $taskId);
        $this->assertEquals('Original Title', $originalTask['title']);

        // Update task
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->putJson("/api/v1/tasks/{$taskId}", [
                'title' => 'Updated Title'
            ]);

        $response->assertStatus(200);

        // Load tasks again - should not use cache
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered');

        $response2->assertStatus(200);
        $tasks = $response2->json('data.tasks');
        $updatedTask = collect($tasks)->firstWhere('id', $taskId);
        $this->assertEquals('Updated Title', $updatedTask['title']);
    }

    /**
     * Test that cache is invalidated on task deletion
     */
    public function test_cache_invalidated_on_task_deletion()
    {
        // Create tasks
        $taskId = $this->createTestTask('Task to Delete', 'TODO');
        $this->createTestTask('Task to Keep', 'TODO');

        // Load tasks to populate cache
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered');

        $response1->assertStatus(200);
        $initialCount = count($response1->json('data.tasks'));

        // Delete task
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->deleteJson("/api/v1/tasks/{$taskId}");

        $response->assertStatus(200);

        // Load tasks again - should not use cache
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered');

        $response2->assertStatus(200);
        $newCount = count($response2->json('data.tasks'));

        // Should have one less task
        $this->assertEquals($initialCount - 1, $newCount);
    }

    /**
     * Test adaptive TTL based on popularity
     */
    public function test_adaptive_ttl_increases_with_popularity()
    {
        // Create test task
        $this->createTestTask('Popular Task', 'TODO');

        // Make multiple requests to increase popularity
        for ($i = 0; $i < 25; $i++) {
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                ->getJson('/api/v1/tasks/filtered?view=kanban');
            
            $response->assertStatus(200);
        }

        // Get cache key and check popularity
        $cacheKey = 'filtered_tasks:' . md5(json_encode([
            'user_id' => $this->userId,
            'user_role' => 'GO',
            'user_org' => $this->organizationId,
            'filters' => [],
            'pagination' => [
                'page' => 1,
                'limit' => 20,
                'sort_by' => 'created_at',
                'sort_direction' => 'desc'
            ],
            'view' => 'kanban'
        ]));

        $popularityKey = "popularity:{$cacheKey}";
        $hitCount = Cache::get($popularityKey, 0);

        // Should have high hit count
        $this->assertGreaterThan(20, $hitCount);
    }

    /**
     * Test different cache keys for different filters
     */
    public function test_different_filters_have_different_cache_keys()
    {
        // Create tasks with different statuses
        $this->createTestTask('Todo Task', 'TODO');
        $this->createTestTask('In Progress Task', 'IN_PROGRESS');
        $this->createTestTask('Done Task', 'DONE');

        // Request with TODO filter
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered?status=TODO');

        $response1->assertStatus(200);
        $todoTasks = $response1->json('data.tasks');

        // Request with DONE filter
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/tasks/filtered?status=DONE');

        $response2->assertStatus(200);
        $doneTasks = $response2->json('data.tasks');

        // Different filters should return different results
        $this->assertNotEquals($todoTasks, $doneTasks);
        $this->assertEquals(1, count($todoTasks));
        $this->assertEquals(1, count($doneTasks));
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
        \DB::table('organization_units')->insert([
            'id' => \Str::uuid()->toString(),
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
        
        \DB::table('positions')->insert([
            'id' => \Str::uuid()->toString(),
            'organization_id' => $orgId,
            'organization_unit_id' => $unit->id,
            'user_id' => $userId,
            'title' => $level . ' Position',
            'level' => $level,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getUserModel($userId)
    {
        return \App\Infrastructure\Persistence\Eloquent\Models\UserModel::find($userId);
    }

    private function getCacheKey($prefix)
    {
        // This is a simplified version - in reality the key includes more data
        return $prefix . ':' . substr(md5($this->userId . $this->organizationId), 0, 8);
    }
}