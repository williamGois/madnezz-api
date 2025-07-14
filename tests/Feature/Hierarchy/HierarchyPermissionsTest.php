<?php

namespace Tests\Feature\Hierarchy;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\Organization\ValueObjects\OrganizationId;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class HierarchyPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private $organizationId;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test organization
        $this->organizationId = $this->createTestOrganization();
    }

    /**
     * Test permission inheritance in hierarchy
     */
    public function test_permission_inheritance()
    {
        // Create users at different levels
        $go = $this->createGO();
        $gr = $this->createGR();
        $storeManager = $this->createStoreManager();

        // GO permissions
        $this->assertEquals(['manage_organization', 'view_all_stores', 'manage_regions'], $go->getPermissions());
        
        // GR permissions
        $this->assertEquals(['manage_region', 'view_stores', 'manage_store_managers'], $gr->getPermissions());
        
        // Store Manager permissions
        $this->assertEquals(['manage_store', 'view_store_data', 'manage_employees'], $storeManager->getPermissions());
    }

    /**
     * Test cross-organization access is denied
     */
    public function test_cross_organization_access_denied()
    {
        // Create two organizations
        $org1Id = $this->organizationId;
        $org2Id = $this->createTestOrganization('ORG2');

        // Create GO for org1
        $go1 = $this->createGO($org1Id);
        $go1Token = $this->getToken($go1);

        // GO from org1 tries to create region in org2
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $go1Token])
            ->postJson("/api/v1/organizations/{$org2Id}/regions", [
                'name' => 'Unauthorized Region',
                'code' => 'UR'
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test department-based access control
     */
    public function test_department_based_access()
    {
        // Create position with specific departments
        $adminPosition = $this->createPositionWithDepartments(['ADM', 'FIN']);
        $commercialPosition = $this->createPositionWithDepartments(['COM', 'MKT']);

        // Test access to administrative reports (requires ADM department)
        $adminToken = $this->getTokenForPosition($adminPosition);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->getJson('/api/v1/reports/administrative');
        
        // Should have access (has ADM department)
        $response->assertStatus(200);

        // Commercial user should not have access
        $commercialToken = $this->getTokenForPosition($commercialPosition);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $commercialToken])
            ->getJson('/api/v1/reports/administrative');
        
        $response->assertStatus(403);
    }

    /**
     * Test hierarchy level restrictions
     */
    public function test_hierarchy_level_restrictions()
    {
        $go = $this->createGO();
        $gr = $this->createGR();
        $storeManager = $this->createStoreManager();

        // Test GO-only endpoint
        $goToken = $this->getToken($go);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $goToken])
            ->getJson('/api/v1/organization/dashboard');
        $response->assertStatus(200);

        // GR should not access GO-only endpoint
        $grToken = $this->getToken($gr);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $grToken])
            ->getJson('/api/v1/organization/dashboard');
        $response->assertStatus(403);

        // Store Manager should not access GR-level endpoint
        $smToken = $this->getToken($storeManager);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $smToken])
            ->getJson('/api/v1/regional/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test data filtering based on hierarchy
     */
    public function test_data_filtering_by_hierarchy()
    {
        // Create structure: 2 regions, 2 stores per region
        $region1Id = $this->createRegion('R1');
        $region2Id = $this->createRegion('R2');
        
        $store1_1 = $this->createStore($region1Id, 'S11');
        $store1_2 = $this->createStore($region1Id, 'S12');
        $store2_1 = $this->createStore($region2Id, 'S21');
        $store2_2 = $this->createStore($region2Id, 'S22');

        // Create GR for region 1
        $gr1 = $this->createGR($region1Id);
        $gr1Token = $this->getToken($gr1);

        // GR1 should only see stores in region 1
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $gr1Token])
            ->getJson("/api/v1/organizations/{$this->organizationId}/regions/{$region1Id}/stores");

        $response->assertStatus(200);
        $stores = $response->json('data.stores');
        
        $this->assertCount(2, $stores);
        $storeCodes = array_column($stores, 'code');
        $this->assertContains('S11', $storeCodes);
        $this->assertContains('S12', $storeCodes);
        $this->assertNotContains('S21', $storeCodes);
        $this->assertNotContains('S22', $storeCodes);
    }

    /**
     * Test MASTER bypasses all restrictions
     */
    public function test_master_bypasses_all_restrictions()
    {
        $master = HierarchicalUser::createMaster(
            new UserName('Master Admin'),
            new Email('master@test.com'),
            new Password('master123')
        );
        $this->saveUser($master);
        $masterToken = $this->getToken($master);

        // MASTER can access any organization
        $otherOrgId = $this->createTestOrganization('OTHER');
        
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $masterToken])
            ->getJson("/api/v1/organizations/{$otherOrgId}/regions");
        
        $response->assertStatus(200);

        // MASTER can create in any organization
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $masterToken])
            ->postJson("/api/v1/organizations/{$otherOrgId}/regions", [
                'name' => 'Master Region',
                'code' => 'MR'
            ]);
        
        $response->assertStatus(201);
    }

    /**
     * Test context middleware injection
     */
    public function test_organization_context_middleware()
    {
        $go = $this->createGO();
        $goToken = $this->getToken($go);

        // Create custom test route to inspect context
        \Route::middleware(['jwt.auth', 'org.context'])->get('/test/context', function (\Illuminate\Http\Request $request) {
            return response()->json($request->input('organization_context'));
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $goToken])
            ->getJson('/test/context');

        $response->assertStatus(200);
        
        $context = $response->json();
        $this->assertEquals($this->organizationId, $context['organization_id']);
        $this->assertEquals('GO', $context['hierarchy_role']);
        $this->assertArrayHasKey('organization_unit_id', $context);
        $this->assertArrayHasKey('departments', $context);
        $this->assertArrayHasKey('permissions', $context);
    }

    // Helper methods
    private function createTestOrganization($code = 'TEST001')
    {
        $orgId = (new OrganizationId())->toString();
        
        \DB::table('organizations')->insert([
            'id' => $orgId,
            'name' => 'Test Organization ' . $code,
            'code' => $code,
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

        // Create default departments
        $departments = ['ADM', 'FIN', 'COM', 'MKT', 'OPS'];
        foreach ($departments as $dept) {
            \DB::table('departments')->insert([
                'id' => \Str::uuid()->toString(),
                'organization_id' => $orgId,
                'name' => $dept,
                'code' => $dept,
                'type' => strtolower($dept),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $orgId;
    }

    private function createGO($orgId = null)
    {
        $orgId = $orgId ?? $this->organizationId;
        
        $go = HierarchicalUser::createGO(
            new UserName('GO User'),
            new Email('go' . uniqid() . '@test.com'),
            new Password('gopass123'),
            new OrganizationId($orgId)
        );
        
        $this->saveUser($go);
        $this->createPosition($go->getId()->toString(), $orgId, 'company', 'GO');
        
        return $go;
    }

    private function createGR($regionId = null)
    {
        $regionId = $regionId ?? $this->createRegion();
        
        $gr = HierarchicalUser::createGR(
            new UserName('GR User'),
            new Email('gr' . uniqid() . '@test.com'),
            new Password('grpass123'),
            new OrganizationId($this->organizationId)
        );
        
        $this->saveUser($gr);
        $this->createPosition($gr->getId()->toString(), $this->organizationId, 'regional', 'GR', $regionId);
        
        return $gr;
    }

    private function createStoreManager($storeId = null)
    {
        $storeId = $storeId ?? $this->createStore();
        
        $sm = HierarchicalUser::createStoreManager(
            new UserName('Store Manager'),
            new Email('sm' . uniqid() . '@test.com'),
            new Password('smpass123'),
            new OrganizationId($this->organizationId),
            new \App\Domain\Organization\ValueObjects\StoreId($storeId)
        );
        
        $this->saveUser($sm);
        
        // Get store unit
        $store = \DB::table('stores')->where('id', $storeId)->first();
        $storeUnit = \DB::table('organization_units')
            ->where('organization_id', $this->organizationId)
            ->where('code', $store->code)
            ->where('type', 'store')
            ->first();
            
        $this->createPosition($sm->getId()->toString(), $this->organizationId, 'store', 'STORE_MANAGER', $storeUnit->id);
        
        return $sm;
    }

    private function createRegion($code = null)
    {
        $code = $code ?? 'R' . uniqid();
        $regionId = \Str::uuid()->toString();
        
        $rootUnit = \DB::table('organization_units')
            ->where('organization_id', $this->organizationId)
            ->where('type', 'company')
            ->first();
        
        \DB::table('organization_units')->insert([
            'id' => $regionId,
            'organization_id' => $this->organizationId,
            'parent_id' => $rootUnit->id,
            'name' => 'Region ' . $code,
            'code' => $code,
            'type' => 'regional',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $regionId;
    }

    private function createStore($regionId = null, $code = null)
    {
        $regionId = $regionId ?? $this->createRegion();
        $code = $code ?? 'S' . uniqid();
        $storeId = \Str::uuid()->toString();
        
        \DB::table('stores')->insert([
            'id' => $storeId,
            'organization_id' => $this->organizationId,
            'name' => 'Store ' . $code,
            'code' => $code,
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        \DB::table('organization_units')->insert([
            'id' => \Str::uuid()->toString(),
            'organization_id' => $this->organizationId,
            'parent_id' => $regionId,
            'name' => 'Store ' . $code,
            'code' => $code,
            'type' => 'store',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $storeId;
    }

    private function createPosition($userId, $orgId, $unitType, $level, $unitId = null)
    {
        if (!$unitId) {
            $unit = \DB::table('organization_units')
                ->where('organization_id', $orgId)
                ->where('type', $unitType)
                ->first();
            $unitId = $unit->id;
        }
        
        $positionId = \Str::uuid()->toString();
        
        \DB::table('positions')->insert([
            'id' => $positionId,
            'organization_id' => $orgId,
            'organization_unit_id' => $unitId,
            'user_id' => $userId,
            'title' => $level . ' Position',
            'level' => $level,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Add default admin department
        $dept = \DB::table('departments')
            ->where('organization_id', $orgId)
            ->where('code', 'ADM')
            ->first();
            
        if ($dept) {
            \DB::table('position_departments')->insert([
                'position_id' => $positionId,
                'department_id' => $dept->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        return $positionId;
    }

    private function createPositionWithDepartments($departmentCodes)
    {
        $userId = \Str::uuid()->toString();
        
        // Create user
        \DB::table('users_ddd')->insert([
            'id' => $userId,
            'name' => 'Test User',
            'email' => 'test' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'hierarchy_role' => 'GO',
            'status' => 'active',
            'organization_id' => $this->organizationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $positionId = $this->createPosition($userId, $this->organizationId, 'company', 'GO');
        
        // Add departments
        foreach ($departmentCodes as $code) {
            $dept = \DB::table('departments')
                ->where('organization_id', $this->organizationId)
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
        
        return $positionId;
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
            'store_id' => $user->getStoreId()?->toString(),
            'phone' => $user->getPhone(),
            'permissions' => json_encode($user->getPermissions()),
            'context_data' => json_encode($user->getContextData()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getToken(HierarchicalUser $user)
    {
        $model = \App\Infrastructure\Persistence\Eloquent\Models\UserModel::find($user->getId()->toString());
        return JWTAuth::fromUser($model);
    }

    private function getTokenForPosition($positionId)
    {
        $position = \DB::table('positions')->where('id', $positionId)->first();
        $model = \App\Infrastructure\Persistence\Eloquent\Models\UserModel::find($position->user_id);
        return JWTAuth::fromUser($model);
    }
}