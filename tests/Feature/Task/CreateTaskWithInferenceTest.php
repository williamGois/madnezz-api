<?php

namespace Tests\Feature\Task;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\Organization\ValueObjects\OrganizationId;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class CreateTaskWithInferenceTest extends TestCase
{
    use RefreshDatabase;

    private $organizationId;
    private $regionId;
    private $storeId;
    private $departmentId;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create organization structure
        $this->organizationId = $this->createTestOrganization();
        $this->regionId = $this->createTestRegion($this->organizationId);
        $this->storeId = $this->createTestStore($this->organizationId, $this->regionId);
        $this->departmentId = $this->createTestDepartment($this->organizationId);
    }

    /**
     * Test that department_id is inferred from user's single department
     */
    public function test_department_id_inferred_from_single_department()
    {
        // Create GO with single department
        $go = HierarchicalUser::createGO(
            new UserName('GO User'),
            new Email('go@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($go);
        $goId = $go->getId()->toString();
        $positionId = $this->createPosition($goId, $this->organizationId, null, 'GO');
        
        // Link only one department
        $this->linkDepartmentToPosition($positionId, $this->departmentId);
        
        $token = JWTAuth::fromUser($this->getUserModel($goId));

        // Create task without specifying department_id
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Test Task',
                'description' => 'Task description',
                'priority' => 'MEDIUM'
            ]);

        $response->assertStatus(201);
        
        // Verify department_id was inferred
        $task = $response->json('data');
        $this->assertEquals($this->departmentId, $task['department_id']);
    }

    /**
     * Test that organization_unit_id is inferred for Store Manager
     */
    public function test_organization_unit_id_inferred_for_store_manager()
    {
        // Create Store Manager
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

        // Create task without specifying organization_unit_id
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Store Task',
                'description' => 'Task for store',
                'priority' => 'HIGH'
            ]);

        $response->assertStatus(201);
        
        // Verify organization_unit_id was inferred
        $task = $response->json('data');
        $this->assertEquals($this->storeId, $task['organization_unit_id']);
    }

    /**
     * Test GO can create task in any unit of their organization
     */
    public function test_go_can_create_task_in_any_unit()
    {
        // Create GO
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

        // Create task in a store (child unit)
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Task for Store',
                'description' => 'GO creating task for store',
                'priority' => 'LOW',
                'organization_unit_id' => $this->storeId
            ]);

        $response->assertStatus(201);
        $task = $response->json('data');
        $this->assertEquals($this->storeId, $task['organization_unit_id']);
    }

    /**
     * Test GR can create task in their region
     */
    public function test_gr_can_create_task_in_their_region()
    {
        // Create GR
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

        // Create task in their region
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Regional Task',
                'description' => 'Task for region',
                'priority' => 'MEDIUM'
            ]);

        $response->assertStatus(201);
        $task = $response->json('data');
        $this->assertEquals($this->regionId, $task['organization_unit_id']);
    }

    /**
     * Test GR can create task in child store
     */
    public function test_gr_can_create_task_in_child_store()
    {
        // Create GR
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

        // Create task in child store
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Store Task by GR',
                'description' => 'GR creating task for store',
                'priority' => 'HIGH',
                'organization_unit_id' => $this->storeId
            ]);

        $response->assertStatus(201);
        $task = $response->json('data');
        $this->assertEquals($this->storeId, $task['organization_unit_id']);
    }

    /**
     * Test Store Manager cannot create task in other stores
     */
    public function test_store_manager_cannot_create_task_in_other_store()
    {
        // Create another store
        $otherStoreId = $this->createTestStore($this->organizationId, $this->regionId);
        
        // Create Store Manager
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

        // Try to create task in another store
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Task for Other Store',
                'description' => 'Should fail',
                'priority' => 'LOW',
                'organization_unit_id' => $otherStoreId
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Store managers can only create tasks in their own store'
        ]);
    }

    /**
     * Test user cannot create task for department they don't have access to
     */
    public function test_user_cannot_create_task_for_inaccessible_department()
    {
        // Create another department
        $otherDeptId = $this->createTestDepartment($this->organizationId, 'FIN', 'Financeiro');
        
        // Create GO with only one department
        $go = HierarchicalUser::createGO(
            new UserName('GO User'),
            new Email('go@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($go);
        $goId = $go->getId()->toString();
        $positionId = $this->createPosition($goId, $this->organizationId, null, 'GO');
        
        // Link only to ADM department
        $this->linkDepartmentToPosition($positionId, $this->departmentId);
        
        $token = JWTAuth::fromUser($this->getUserModel($goId));

        // Try to create task for FIN department
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Finance Task',
                'description' => 'Should fail',
                'priority' => 'HIGH',
                'department_id' => $otherDeptId
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Cannot create task for department you do not have access to'
        ]);
    }

    /**
     * Test explicit values override inference
     */
    public function test_explicit_values_override_inference()
    {
        // Create another department
        $otherDeptId = $this->createTestDepartment($this->organizationId, 'MKT', 'Marketing');
        
        // Create GO with multiple departments
        $go = HierarchicalUser::createGO(
            new UserName('GO User'),
            new Email('go@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($go);
        $goId = $go->getId()->toString();
        $positionId = $this->createPosition($goId, $this->organizationId, null, 'GO');
        
        // Link both departments
        $this->linkDepartmentToPosition($positionId, $this->departmentId);
        $this->linkDepartmentToPosition($positionId, $otherDeptId);
        
        $token = JWTAuth::fromUser($this->getUserModel($goId));

        // Create task with explicit department_id
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/tasks', [
                'title' => 'Marketing Task',
                'description' => 'Task for marketing',
                'priority' => 'MEDIUM',
                'department_id' => $otherDeptId,
                'organization_unit_id' => $this->regionId
            ]);

        $response->assertStatus(201);
        
        $task = $response->json('data');
        $this->assertEquals($otherDeptId, $task['department_id']);
        $this->assertEquals($this->regionId, $task['organization_unit_id']);
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
        $rootUnit = \DB::table('organization_units')
            ->where('organization_id', $orgId)
            ->where('type', 'company')
            ->first();
        
        \DB::table('organization_units')->insert([
            'id' => $regionId,
            'organization_id' => $orgId,
            'name' => 'Test Region',
            'code' => 'REGION01',
            'type' => 'regional',
            'parent_id' => $rootUnit->id,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $regionId;
    }

    private function createTestStore($orgId, $regionId)
    {
        $storeId = \Str::uuid()->toString();
        $storeCode = 'STORE' . substr($storeId, 0, 6);
        
        \DB::table('organization_units')->insert([
            'id' => $storeId,
            'organization_id' => $orgId,
            'name' => 'Test Store ' . substr($storeId, 0, 8),
            'code' => $storeCode,
            'type' => 'store',
            'parent_id' => $regionId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Also create store record
        \DB::table('stores')->insert([
            'id' => \Str::uuid()->toString(),
            'organization_id' => $orgId,
            'code' => $storeCode,
            'name' => 'Test Store ' . substr($storeId, 0, 8),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $storeId;
    }

    private function createTestDepartment($orgId, $code = 'ADM', $name = 'Administração')
    {
        $deptId = \Str::uuid()->toString();
        
        \DB::table('departments')->insert([
            'id' => $deptId,
            'organization_id' => $orgId,
            'name' => $name,
            'code' => $code,
            'type' => 'administrative',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $deptId;
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
        
        $positionId = \Str::uuid()->toString();
        
        \DB::table('positions')->insert([
            'id' => $positionId,
            'organization_id' => $orgId,
            'organization_unit_id' => $unit,
            'user_id' => $userId,
            'title' => $level . ' Position',
            'level' => $level,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $positionId;
    }

    private function linkDepartmentToPosition($positionId, $departmentId)
    {
        \DB::table('position_departments')->insert([
            'position_id' => $positionId,
            'department_id' => $departmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getUserModel($userId)
    {
        return \App\Infrastructure\Persistence\Eloquent\Models\UserModel::find($userId);
    }
}