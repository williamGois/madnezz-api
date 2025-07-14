<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Http\Middleware\VisibleStoresMiddleware;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VisibleStoresMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private $middleware;
    private $organizationId;
    private $regionId;
    private $storeId1;
    private $storeId2;
    private $otherRegionId;
    private $otherStoreId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->middleware = new VisibleStoresMiddleware();
        
        // Create test organization structure
        $this->createTestOrganizationStructure();
        
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test MASTER user can see all stores
     */
    public function test_master_user_can_see_all_stores()
    {
        $master = $this->createTestUser('MASTER', null);
        $request = $this->createAuthenticatedRequest($master);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json([
                'visible_store_ids' => $req->attributes->get('visible_store_ids')
            ]);
        });
        
        $visibleStoreIds = json_decode($response->getContent(), true)['visible_store_ids'];
        
        // Should see all 3 stores created
        $this->assertCount(3, $visibleStoreIds);
        $this->assertContains($this->storeId1, $visibleStoreIds);
        $this->assertContains($this->storeId2, $visibleStoreIds);
        $this->assertContains($this->otherStoreId, $visibleStoreIds);
    }

    /**
     * Test GO user can see all stores in their organization
     */
    public function test_go_user_can_see_organization_stores()
    {
        $go = $this->createTestUser('GO', $this->organizationId);
        $request = $this->createAuthenticatedRequest($go);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json([
                'visible_store_ids' => $req->attributes->get('visible_store_ids')
            ]);
        });
        
        $visibleStoreIds = json_decode($response->getContent(), true)['visible_store_ids'];
        
        // Should see only the 2 stores in their organization
        $this->assertCount(2, $visibleStoreIds);
        $this->assertContains($this->storeId1, $visibleStoreIds);
        $this->assertContains($this->storeId2, $visibleStoreIds);
        $this->assertNotContains($this->otherStoreId, $visibleStoreIds);
    }

    /**
     * Test GR user can see stores in their region
     */
    public function test_gr_user_can_see_region_stores()
    {
        $gr = $this->createTestUser('GR', $this->organizationId);
        $this->createUserPosition($gr->id, $this->regionId);
        
        $request = $this->createAuthenticatedRequest($gr);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json([
                'visible_store_ids' => $req->attributes->get('visible_store_ids')
            ]);
        });
        
        $visibleStoreIds = json_decode($response->getContent(), true)['visible_store_ids'];
        
        // Should see only the 1 store in their region
        $this->assertCount(1, $visibleStoreIds);
        $this->assertContains($this->storeId1, $visibleStoreIds);
        $this->assertNotContains($this->storeId2, $visibleStoreIds);
    }

    /**
     * Test Store Manager can see only their store
     */
    public function test_store_manager_can_see_only_their_store()
    {
        $storeManager = $this->createTestUser('STORE_MANAGER', $this->organizationId);
        
        // Get store unit ID
        $storeUnit = DB::table('organization_units')
            ->where('organization_id', $this->organizationId)
            ->where('code', 'STORE001')
            ->where('type', 'store')
            ->first();
            
        $this->createUserPosition($storeManager->id, $storeUnit->id);
        
        $request = $this->createAuthenticatedRequest($storeManager);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json([
                'visible_store_ids' => $req->attributes->get('visible_store_ids')
            ]);
        });
        
        $visibleStoreIds = json_decode($response->getContent(), true)['visible_store_ids'];
        
        // Should see only their own store
        $this->assertCount(1, $visibleStoreIds);
        $this->assertContains($this->storeId1, $visibleStoreIds);
    }

    /**
     * Test caching works correctly
     */
    public function test_visible_stores_are_cached()
    {
        $go = $this->createTestUser('GO', $this->organizationId);
        $request = $this->createAuthenticatedRequest($go);
        
        // First call - should query database
        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        // Check cache exists
        $cacheKey = "visible_stores:{$go->id}";
        $this->assertTrue(Cache::has($cacheKey));
        
        // Delete a store from database
        DB::table('stores')->where('id', $this->storeId2)->delete();
        
        // Second call - should use cache and still return 2 stores
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json([
                'visible_store_ids' => $req->attributes->get('visible_store_ids')
            ]);
        });
        
        $visibleStoreIds = json_decode($response->getContent(), true)['visible_store_ids'];
        $this->assertCount(2, $visibleStoreIds); // Still 2 from cache
    }

    /**
     * Test user with no position gets empty array
     */
    public function test_user_with_no_position_gets_empty_stores()
    {
        $gr = $this->createTestUser('GR', $this->organizationId);
        // Don't create position
        
        $request = $this->createAuthenticatedRequest($gr);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json([
                'visible_store_ids' => $req->attributes->get('visible_store_ids')
            ]);
        });
        
        $visibleStoreIds = json_decode($response->getContent(), true)['visible_store_ids'];
        $this->assertCount(0, $visibleStoreIds);
    }

    // Helper methods
    private function createTestOrganizationStructure()
    {
        // Create organization
        $this->organizationId = \Str::uuid()->toString();
        DB::table('organizations')->insert([
            'id' => $this->organizationId,
            'name' => 'Test Organization',
            'code' => 'TEST001',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create root unit
        $rootId = \Str::uuid()->toString();
        DB::table('organization_units')->insert([
            'id' => $rootId,
            'organization_id' => $this->organizationId,
            'name' => 'Sede',
            'code' => 'SEDE',
            'type' => 'company',
            'parent_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create region
        $this->regionId = \Str::uuid()->toString();
        DB::table('organization_units')->insert([
            'id' => $this->regionId,
            'organization_id' => $this->organizationId,
            'name' => 'Region 1',
            'code' => 'REG001',
            'type' => 'regional',
            'parent_id' => $rootId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create another region
        $this->otherRegionId = \Str::uuid()->toString();
        DB::table('organization_units')->insert([
            'id' => $this->otherRegionId,
            'organization_id' => $this->organizationId,
            'name' => 'Region 2',
            'code' => 'REG002',
            'type' => 'regional',
            'parent_id' => $rootId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create stores
        $this->createStore($this->regionId, 'STORE001', 'Store 1');
        $this->storeId1 = DB::table('stores')->where('code', 'STORE001')->first()->id;
        
        $this->createStore($this->otherRegionId, 'STORE002', 'Store 2');
        $this->storeId2 = DB::table('stores')->where('code', 'STORE002')->first()->id;
        
        // Create store in different organization
        $otherOrgId = \Str::uuid()->toString();
        DB::table('organizations')->insert([
            'id' => $otherOrgId,
            'name' => 'Other Organization',
            'code' => 'OTHER001',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->createStore(null, 'STORE003', 'Store 3', $otherOrgId);
        $this->otherStoreId = DB::table('stores')->where('code', 'STORE003')->first()->id;
    }

    private function createStore($regionId, $code, $name, $orgId = null)
    {
        $orgId = $orgId ?: $this->organizationId;
        $storeUnitId = \Str::uuid()->toString();
        
        // Create organization unit for store
        if ($regionId) {
            DB::table('organization_units')->insert([
                'id' => $storeUnitId,
                'organization_id' => $orgId,
                'name' => $name,
                'code' => $code,
                'type' => 'store',
                'parent_id' => $regionId,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // Create store record
        DB::table('stores')->insert([
            'id' => \Str::uuid()->toString(),
            'organization_id' => $orgId,
            'code' => $code,
            'name' => $name,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTestUser($role, $organizationId)
    {
        return UserModel::create([
            'name' => $role . ' User',
            'email' => strtolower($role) . '@test.com',
            'password' => bcrypt('password'),
            'hierarchy_role' => $role,
            'organization_id' => $organizationId,
            'status' => 'active'
        ]);
    }

    private function createUserPosition($userId, $unitId)
    {
        DB::table('positions')->insert([
            'id' => \Str::uuid()->toString(),
            'user_id' => $userId,
            'organization_id' => $this->organizationId,
            'organization_unit_id' => $unitId,
            'title' => 'Test Position',
            'level' => 'TEST',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAuthenticatedRequest($user)
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        return $request;
    }
}