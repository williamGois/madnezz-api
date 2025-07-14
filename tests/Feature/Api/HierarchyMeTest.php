<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\Organization\Entities\Organization;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\Organization\ValueObjects\OrganizationId;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class HierarchyMeTest extends TestCase
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
     * Test MASTER user can access hierarchy/me endpoint
     */
    public function test_master_user_can_access_hierarchy_me()
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

        // Request hierarchy/me
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/hierarchy/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'hierarchy_role'
                ],
                'organization',
                'position',
                'hierarchy_path'
            ]
        ]);
        
        $data = $response->json('data');
        $this->assertEquals('MASTER', $data['user']['hierarchy_role']);
        $this->assertEquals('Master User', $data['user']['name']);
        $this->assertEquals('master@test.com', $data['user']['email']);
    }

    /**
     * Test GO user gets complete hierarchy information
     */
    public function test_go_user_gets_complete_hierarchy_info()
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

        // Request hierarchy/me
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/hierarchy/me');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Check user info
        $this->assertEquals('GO', $data['user']['hierarchy_role']);
        $this->assertEquals('GO User', $data['user']['name']);
        
        // Check organization info
        $this->assertNotNull($data['organization']);
        $this->assertEquals($this->organizationId, $data['organization']['id']);
        $this->assertEquals('Test Organization', $data['organization']['name']);
        $this->assertEquals('TEST001', $data['organization']['code']);
        
        // Check position info
        $this->assertNotNull($data['position']);
        $this->assertEquals('GO', $data['position']['level']);
        $this->assertEquals('Sede', $data['position']['unit_name']);
        $this->assertEquals('company', $data['position']['unit_type']);
        
        // Check hierarchy path
        $this->assertIsArray($data['hierarchy_path']);
        $this->assertGreaterThan(0, count($data['hierarchy_path']));
    }

    /**
     * Test GR user gets region hierarchy information
     */
    public function test_gr_user_gets_region_hierarchy_info()
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

        // Request hierarchy/me
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/hierarchy/me');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Check user info
        $this->assertEquals('GR', $data['user']['hierarchy_role']);
        
        // Check position shows region
        $this->assertEquals('GR', $data['position']['level']);
        $this->assertEquals('Test Region', $data['position']['unit_name']);
        $this->assertEquals('regional', $data['position']['unit_type']);
        $this->assertEquals($this->regionId, $data['position']['unit_id']);
        
        // Check hierarchy path includes organization and region
        $hierarchyPath = $data['hierarchy_path'];
        $this->assertCount(3, $hierarchyPath); // Organization -> Sede -> Region
        
        // Verify path order
        $this->assertEquals('organization', $hierarchyPath[0]['level']);
        $this->assertEquals('company', $hierarchyPath[1]['level']);
        $this->assertEquals('regional', $hierarchyPath[2]['level']);
    }

    /**
     * Test Store Manager gets store hierarchy information
     */
    public function test_store_manager_gets_store_hierarchy_info()
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

        // Request hierarchy/me
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/hierarchy/me');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Check user info
        $this->assertEquals('STORE_MANAGER', $data['user']['hierarchy_role']);
        
        // Check position shows store
        $this->assertEquals('STORE_MANAGER', $data['position']['level']);
        $this->assertEquals('Test Store', $data['position']['unit_name']);
        $this->assertEquals('store', $data['position']['unit_type']);
        $this->assertEquals($this->storeId, $data['position']['unit_id']);
        
        // Check hierarchy path includes full path
        $hierarchyPath = $data['hierarchy_path'];
        $this->assertCount(4, $hierarchyPath); // Organization -> Sede -> Region -> Store
        
        // Verify complete path
        $this->assertEquals('organization', $hierarchyPath[0]['level']);
        $this->assertEquals('company', $hierarchyPath[1]['level']);
        $this->assertEquals('regional', $hierarchyPath[2]['level']);
        $this->assertEquals('store', $hierarchyPath[3]['level']);
    }

    /**
     * Test that departments are included in response
     */
    public function test_departments_are_included_in_response()
    {
        // Create GO user with departments
        $go = HierarchicalUser::createGO(
            new UserName('GO User'),
            new Email('go@test.com'),
            new Password('password123'),
            new OrganizationId($this->organizationId)
        );
        $this->saveUser($go);
        $goId = $go->getId()->toString();
        $positionId = $this->createPosition($goId, $this->organizationId, null, 'GO');
        
        // Link departments to position
        $this->linkDepartmentsToPosition($positionId, ['ADM', 'FIN']);
        
        $token = JWTAuth::fromUser($this->getUserModel($goId));

        // Request hierarchy/me
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/hierarchy/me');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Check departments
        $this->assertIsArray($data['departments']);
        $this->assertCount(2, $data['departments']);
        
        // Verify department codes
        $deptCodes = array_column($data['departments'], 'code');
        $this->assertContains('ADM', $deptCodes);
        $this->assertContains('FIN', $deptCodes);
    }

    /**
     * Test unauthenticated access is denied
     */
    public function test_unauthenticated_access_is_denied()
    {
        $response = $this->getJson('/api/v1/hierarchy/me');
        $response->assertStatus(401);
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
        $this->createDepartments($orgId);

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
        
        \DB::table('organization_units')->insert([
            'id' => $storeId,
            'organization_id' => $orgId,
            'name' => 'Test Store',
            'code' => 'STORE001',
            'type' => 'store',
            'parent_id' => $regionId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $storeId;
    }

    private function createDepartments($orgId)
    {
        $departments = [
            ['code' => 'ADM', 'name' => 'Administração', 'type' => 'administrative'],
            ['code' => 'FIN', 'name' => 'Financeiro', 'type' => 'financial'],
        ];

        foreach ($departments as $dept) {
            \DB::table('departments')->insert([
                'id' => \Str::uuid()->toString(),
                'organization_id' => $orgId,
                'name' => $dept['name'],
                'code' => $dept['code'],
                'type' => $dept['type'],
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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

    private function linkDepartmentsToPosition($positionId, $deptCodes)
    {
        foreach ($deptCodes as $code) {
            $dept = \DB::table('departments')
                ->where('code', $code)
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