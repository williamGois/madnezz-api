<?php

namespace Tests\Feature\Hierarchy;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\Organization\Entities\Organization;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class OrganizationHierarchyFlowTest extends TestCase
{
    use RefreshDatabase;

    private $masterToken;
    private $goToken;
    private $grToken;
    private $storeManagerToken;
    
    private $organizationId;
    private $regionId;
    private $storeId;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create MASTER user
        $master = HierarchicalUser::createMaster(
            new UserName('Master Admin'),
            new Email('master@madnezz.com'),
            new Password('master123')
        );
        $this->saveUser($master);
        $this->masterToken = JWTAuth::fromUser($this->getUserModel($master->getId()->toString()));
    }

    /**
     * Test complete hierarchy flow: MASTER → Organization → GO → Region → GR → Store → Store Manager
     */
    public function test_complete_hierarchy_creation_flow()
    {
        // Step 1: MASTER creates organization with GO
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->masterToken])
            ->postJson('/api/v1/organizations', [
                'name' => 'Test Organization',
                'code' => 'TEST001',
                'go_user' => [
                    'name' => 'GO User',
                    'email' => 'go@test.com',
                    'password' => 'gopass123',
                    'phone' => '+5511999999999'
                ]
            ]);

        $response->assertStatus(201);
        $this->organizationId = $response->json('data.organization.id');
        $goUserId = $response->json('data.go_user_id');
        
        // Login as GO
        $goLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'go@test.com',
            'password' => 'gopass123'
        ]);
        $this->goToken = $goLoginResponse->json('access_token');

        // Step 2: GO creates region
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->goToken])
            ->postJson("/api/v1/organizations/{$this->organizationId}/regions", [
                'name' => 'Região Sul',
                'code' => 'RS'
            ]);

        $response->assertStatus(201);
        $this->regionId = $response->json('data.region.id');

        // Step 3: GO creates GR for the region
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->goToken])
            ->postJson("/api/v1/organizations/{$this->organizationId}/regions/{$this->regionId}/gr", [
                'name' => 'GR User',
                'email' => 'gr@test.com',
                'password' => 'grpass123',
                'phone' => '+5511888888888'
            ]);

        $response->assertStatus(201);
        $grUserId = $response->json('data.user.id');

        // Login as GR
        $grLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'gr@test.com',
            'password' => 'grpass123'
        ]);
        $this->grToken = $grLoginResponse->json('access_token');

        // Step 4: GO creates store with Store Manager
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->goToken])
            ->postJson("/api/v1/organizations/{$this->organizationId}/stores", [
                'region_id' => $this->regionId,
                'name' => 'Loja Centro',
                'code' => 'LJ001',
                'address' => 'Rua Principal, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01234-567',
                'phone' => '+5511333333333',
                'manager' => [
                    'name' => 'Store Manager',
                    'email' => 'manager@store.com',
                    'password' => 'manager123',
                    'phone' => '+5511777777777'
                ]
            ]);

        $response->assertStatus(201);
        $this->storeId = $response->json('data.store.id');

        // Login as Store Manager
        $managerLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@store.com',
            'password' => 'manager123'
        ]);
        $this->storeManagerToken = $managerLoginResponse->json('access_token');

        // Verify hierarchy is properly created
        $this->assertNotNull($this->organizationId);
        $this->assertNotNull($this->regionId);
        $this->assertNotNull($this->storeId);
        $this->assertNotNull($this->storeManagerToken);
    }

    /**
     * Test access control: GR can only see stores in their region
     */
    public function test_gr_can_only_access_stores_in_their_region()
    {
        $this->test_complete_hierarchy_creation_flow();

        // GR should see stores in their region
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->grToken])
            ->getJson("/api/v1/organizations/{$this->organizationId}/regions/{$this->regionId}/stores");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.stores');
        $response->assertJsonPath('data.stores.0.id', $this->storeId);

        // Create another region as GO
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->goToken])
            ->postJson("/api/v1/organizations/{$this->organizationId}/regions", [
                'name' => 'Região Norte',
                'code' => 'RN'
            ]);

        $otherRegionId = $response->json('data.region.id');

        // GR should not access stores in other regions
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->grToken])
            ->getJson("/api/v1/organizations/{$this->organizationId}/regions/{$otherRegionId}/stores");

        $response->assertStatus(403);
    }

    /**
     * Test access control: Store Manager can only access their store
     */
    public function test_store_manager_limited_access()
    {
        $this->test_complete_hierarchy_creation_flow();

        // Create another store as GO
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->goToken])
            ->postJson("/api/v1/organizations/{$this->organizationId}/stores", [
                'region_id' => $this->regionId,
                'name' => 'Loja Norte',
                'code' => 'LJ002',
                'address' => 'Rua Norte, 456',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01234-568'
            ]);

        $otherStoreId = $response->json('data.store.id');

        // Store Manager cannot list all stores in region
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->storeManagerToken])
            ->getJson("/api/v1/organizations/{$this->organizationId}/regions/{$this->regionId}/stores");

        $response->assertStatus(403);
    }

    /**
     * Test MASTER has access to everything
     */
    public function test_master_has_universal_access()
    {
        $this->test_complete_hierarchy_creation_flow();

        // MASTER can list all organizations
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->masterToken])
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200);

        // MASTER can list regions
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->masterToken])
            ->getJson("/api/v1/organizations/{$this->organizationId}/regions");

        $response->assertStatus(200);

        // MASTER can list stores in any region
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->masterToken])
            ->getJson("/api/v1/organizations/{$this->organizationId}/regions/{$this->regionId}/stores");

        $response->assertStatus(200);
    }

    /**
     * Test GO cannot create organizations (only MASTER can)
     */
    public function test_go_cannot_create_organizations()
    {
        $this->test_complete_hierarchy_creation_flow();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->goToken])
            ->postJson('/api/v1/organizations', [
                'name' => 'Another Organization',
                'code' => 'TEST002'
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test GR cannot create regions (only GO/MASTER can)
     */
    public function test_gr_cannot_create_regions()
    {
        $this->test_complete_hierarchy_creation_flow();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->grToken])
            ->postJson("/api/v1/organizations/{$this->organizationId}/regions", [
                'name' => 'Another Region',
                'code' => 'AR'
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test Store Manager cannot create stores
     */
    public function test_store_manager_cannot_create_stores()
    {
        $this->test_complete_hierarchy_creation_flow();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->storeManagerToken])
            ->postJson("/api/v1/organizations/{$this->organizationId}/stores", [
                'region_id' => $this->regionId,
                'name' => 'Another Store',
                'code' => 'LJ003',
                'address' => 'Rua Test, 789',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01234-569'
            ]);

        $response->assertStatus(403);
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

    private function getUserModel($userId)
    {
        return \App\Infrastructure\Persistence\Eloquent\Models\UserModel::find($userId);
    }
}