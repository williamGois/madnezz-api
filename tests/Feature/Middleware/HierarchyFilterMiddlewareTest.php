<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\Organization\Entities\Organization;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\Organization\ValueObjects\OrganizationId;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class HierarchyFilterMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private $organizationId;
    private $regionId;
    private $storeId;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test organization structure
        $this->organizationId = $this->createTestOrganization();
        $this->regionId = $this->createTestRegion($this->organizationId);
        $this->storeId = $this->createTestStore($this->organizationId, $this->regionId);
    }

    /**
     * Test MASTER user sees all tasks without filters
     */
    public function test_master_user_has_no_filters()
    {
        // Create MASTER user
        $master = HierarchicalUser::createMaster(
            new UserName('Master User'),
            new Email('master@test.com'),
            new Password('password123')
        );
        $this->saveUser($master);
        $masterId = $master->getId()->toString();
        
        $token = JWTAuth::fromUser($this->getUserModel($masterId));

        // Create tasks at different levels
        $this->createTestTask('Org Task', $this->organizationId, null);
        $this->createTestTask('Region Task', $this->organizationId, $this->regionId);
        $this->createTestTask('Store Task', $this->organizationId, $this->storeId);

        // Request tasks
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/tasks');

        $response->assertStatus(200);
        
        // MASTER should see all tasks
        $tasks = $response->json('data');
        $this->assertCount(3, $tasks);
    }

    /**
     * Test GO user sees only organization tasks
     */
    public function test_go_user_sees_only_organization_tasks()
    {
        // Create GO user
        $go = HierarchicalUser::createGO(
            new UserName('GO User'),
            new Email('go@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($go);
        $goId = $go->getId()->toString();
        $this->createPosition($goId, $this->organizationId, null, 'GO');
        
        $token = JWTAuth::fromUser($this->getUserModel($goId));

        // Create tasks
        $this->createTestTask('My Org Task 1', $this->organizationId, null);
        $this->createTestTask('My Org Task 2', $this->organizationId, $this->regionId);
        $this->createTestTask('Other Org Task', \Str::uuid()->toString(), null);

        // Request tasks
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/tasks');

        $response->assertStatus(200);
        
        // GO should see only tasks from their organization
        $tasks = $response->json('data');
        $this->assertCount(2, $tasks);
    }

    /**
     * Test GR user sees region and child store tasks
     */
    public function test_gr_user_sees_region_and_store_tasks()
    {
        // Create GR user
        $gr = HierarchicalUser::createGR(
            new UserName('GR User'),
            new Email('gr@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($gr);
        $grId = $gr->getId()->toString();
        $this->createPosition($grId, $this->organizationId, $this->regionId, 'GR');
        
        $token = JWTAuth::fromUser($this->getUserModel($grId));

        // Create another store in the same region
        $store2Id = $this->createTestStore($this->organizationId, $this->regionId);

        // Create tasks
        $this->createTestTask('Org Level Task', $this->organizationId, null);
        $this->createTestTask('Region Task', $this->organizationId, $this->regionId);
        $this->createTestTask('Store 1 Task', $this->organizationId, $this->storeId);
        $this->createTestTask('Store 2 Task', $this->organizationId, $store2Id);
        $this->createTestTask('Other Region Task', $this->organizationId, \Str::uuid()->toString());

        // Request tasks
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/tasks');

        $response->assertStatus(200);
        
        // GR should see region task and both store tasks (3 total)
        $tasks = $response->json('data');
        $this->assertCount(3, $tasks);
    }

    /**
     * Test Store Manager sees only store tasks
     */
    public function test_store_manager_sees_only_store_tasks()
    {
        // Create Store Manager user
        $sm = HierarchicalUser::createStoreManager(
            new UserName('Store Manager'),
            new Email('sm@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($sm);
        $smId = $sm->getId()->toString();
        $this->createPosition($smId, $this->organizationId, $this->storeId, 'STORE_MANAGER');
        
        $token = JWTAuth::fromUser($this->getUserModel($smId));

        // Create tasks
        $this->createTestTask('Org Task', $this->organizationId, null);
        $this->createTestTask('Region Task', $this->organizationId, $this->regionId);
        $this->createTestTask('My Store Task 1', $this->organizationId, $this->storeId);
        $this->createTestTask('My Store Task 2', $this->organizationId, $this->storeId);
        $this->createTestTask('Other Store Task', $this->organizationId, \Str::uuid()->toString());

        // Request tasks
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/tasks');

        $response->assertStatus(200);
        
        // Store Manager should see only their store tasks
        $tasks = $response->json('data');
        $this->assertCount(2, $tasks);
    }

    /**
     * Test hierarchy filter is applied to kanban board
     */
    public function test_hierarchy_filter_applied_to_kanban_board()
    {
        // Create GR user
        $gr = HierarchicalUser::createGR(
            new UserName('GR User'),
            new Email('gr@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($gr);
        $grId = $gr->getId()->toString();
        $this->createPosition($grId, $this->organizationId, $this->regionId, 'GR');
        
        $token = JWTAuth::fromUser($this->getUserModel($grId));

        // Create tasks with different statuses
        $this->createTestTask('Region TODO', $this->organizationId, $this->regionId, 'TODO');
        $this->createTestTask('Store IN_PROGRESS', $this->organizationId, $this->storeId, 'IN_PROGRESS');
        $this->createTestTask('Other Region DONE', $this->organizationId, \Str::uuid()->toString(), 'DONE');

        // Request kanban board
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/tasks/kanban');

        $response->assertStatus(200);
        
        $board = $response->json('data.board');
        
        // Check that only region and store tasks are included
        $this->assertCount(1, $board['TODO']['tasks']);
        $this->assertCount(1, $board['IN_PROGRESS']['tasks']);
        $this->assertCount(0, $board['DONE']['tasks']);
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

    private function createTestRegion($orgId)
    {
        $regionId = \Str::uuid()->toString();
        
        \DB::table('organization_units')->insert([
            'id' => $regionId,
            'organization_id' => $orgId,
            'name' => 'Test Region',
            'code' => 'REGION01',
            'type' => 'regional',
            'parent_id' => \DB::table('organization_units')
                ->where('organization_id', $orgId)
                ->where('type', 'company')
                ->first()->id,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $regionId;
    }

    private function createTestStore($orgId, $regionId)
    {
        $storeId = \Str::uuid()->toString();
        
        \DB::table('organization_units')->insert([
            'id' => $storeId,
            'organization_id' => $orgId,
            'name' => 'Test Store ' . substr($storeId, 0, 8),
            'code' => 'STORE' . substr($storeId, 0, 6),
            'type' => 'store',
            'parent_id' => $regionId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $storeId;
    }

    private function createTestTask($title, $orgId, $unitId, $status = 'TODO')
    {
        $taskId = \Str::uuid()->toString();
        
        \DB::table('tasks')->insert([
            'id' => $taskId,
            'title' => $title,
            'description' => 'Test task description',
            'status' => $status,
            'priority' => 'MEDIUM',
            'created_by' => \Str::uuid()->toString(),
            'organization_id' => $orgId,
            'organization_unit_id' => $unitId,
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

    private function createPosition($userId, $orgId, $unitId, $level)
    {
        $unit = $unitId ?: \DB::table('organization_units')
            ->where('organization_id', $orgId)
            ->where('type', 'company')
            ->first()->id;
        
        \DB::table('positions')->insert([
            'id' => \Str::uuid()->toString(),
            'organization_id' => $orgId,
            'organization_unit_id' => $unit,
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
}