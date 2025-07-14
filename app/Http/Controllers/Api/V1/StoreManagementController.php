<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Application\UseCases\CreateStore\CreateStoreCommand;
use App\Application\UseCases\CreateStore\CreateStoreUseCase;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\User\ValueObjects\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoreManagementController extends BaseController
{
    public function __construct(
        private readonly CreateStoreUseCase $createStoreUseCase,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly OrganizationUnitRepositoryInterface $unitRepository
    ) {}

    /**
     * Create a new store (GO or MASTER only)
     * 
     * @OA\Post(
     *     path="/api/v1/organizations/{org_id}/stores",
     *     summary="Create a new store",
     *     tags={"Stores"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="org_id",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"region_id", "name", "code", "address", "city", "state", "zip_code"},
     *             @OA\Property(property="region_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
     *             @OA\Property(property="name", type="string", example="Loja Centro"),
     *             @OA\Property(property="code", type="string", example="LJ001"),
     *             @OA\Property(property="address", type="string", example="Rua Principal, 123"),
     *             @OA\Property(property="city", type="string", example="São Paulo"),
     *             @OA\Property(property="state", type="string", example="SP"),
     *             @OA\Property(property="zip_code", type="string", example="01234-567"),
     *             @OA\Property(property="phone", type="string", example="+5511912345678"),
     *             @OA\Property(property="manager", type="object",
     *                 @OA\Property(property="name", type="string", example="João Silva"),
     *                 @OA\Property(property="email", type="string", format="email", example="manager@store.com"),
     *                 @OA\Property(property="password", type="string", format="password", example="SecurePass123!"),
     *                 @OA\Property(property="phone", type="string", example="+5511999999999")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Store created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden - Only MASTER or GO users can create stores")
     * )
     */
    public function store(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || (!in_array($user->hierarchy_role, ['MASTER', 'GO']))) {
            return $this->errorResponse('Access denied. Only MASTER or GO users can create stores.', 403);
        }

        $validator = Validator::make($request->all(), [
            'region_id' => 'required|uuid',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:2',
            'zip_code' => 'required|string|max:10',
            'phone' => 'nullable|string|max:20',
            'manager' => 'sometimes|array',
            'manager.name' => 'required_with:manager|string|max:255',
            'manager.email' => 'required_with:manager|email|unique:users_ddd,email',
            'manager.password' => 'required_with:manager|string|min:8',
            'manager.phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        DB::beginTransaction();
        
        try {
            // Prepare manager data if provided
            $managerData = null;
            if ($request->has('manager')) {
                $mgr = $request->input('manager');
                $managerData = [
                    'name' => new UserName($mgr['name']),
                    'email' => new Email($mgr['email']),
                    'password' => new Password($mgr['password']),
                    'phone' => isset($mgr['phone']) ? new Phone($mgr['phone']) : null,
                ];
            }

            // Create store command
            $command = new CreateStoreCommand(
                new UserId($user->id),
                new OrganizationId($orgId),
                new OrganizationUnitId($request->input('region_id')),
                $request->input('name'),
                $request->input('code'),
                $request->input('address'),
                $request->input('city'),
                $request->input('state'),
                $request->input('zip_code'),
                $request->input('phone'),
                $managerData
            );

            // Execute use case
            $response = $this->createStoreUseCase->execute($command);
            
            DB::commit();

            return $this->successResponse([
                'store' => [
                    'id' => $response->getStoreId(),
                    'name' => $response->getName(),
                    'code' => $response->getCode(),
                    'organization_id' => $response->getOrganizationId(),
                    'store_unit_id' => $response->getStoreUnitId(),
                ],
                'manager_id' => $response->getManagerUserId(),
                'message' => 'Store created successfully'
            ], 201);

        } catch (\DomainException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create store: ' . $e->getMessage());
            return $this->errorResponse('Failed to create store', 500);
        }
    }

    /**
     * List stores in a region
     * 
     * @OA\Get(
     *     path="/api/v1/organizations/{org_id}/regions/{region_id}/stores",
     *     summary="List all stores in a region",
     *     tags={"Stores"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="org_id",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="region_id",
     *         in="path",
     *         required=true,
     *         description="Region ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function listByRegion(Request $request, string $orgId, string $regionId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || (!in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR']))) {
            return $this->errorResponse('Access denied.', 403);
        }

        try {
            $organizationId = new OrganizationId($orgId);
            $regionUnitId = new OrganizationUnitId($regionId);
            
            // If GO or GR, verify they belong to this organization
            if (in_array($user->hierarchy_role, ['GO', 'GR']) && $user->organization_id !== $orgId) {
                return $this->errorResponse('Access denied to this organization.', 403);
            }

            // Verify region exists and is of type regional
            $region = $this->unitRepository->findById($regionUnitId);
            if (!$region || $region->getType() !== 'regional') {
                return $this->errorResponse('Region not found', 404);
            }

            // Get all store units under this region
            $storeUnits = $this->unitRepository->findChildren($regionUnitId);
            $storeUnits = array_filter($storeUnits, fn($unit) => $unit->getType() === 'store');

            // Get actual store data for each store unit
            $stores = [];
            foreach ($storeUnits as $storeUnit) {
                $store = $this->storeRepository->findByCode($storeUnit->getCode());
                if ($store) {
                    $stores[] = [
                        'id' => $store->getId()->toString(),
                        'name' => $store->getName(),
                        'code' => $store->getCode(),
                        'address' => $store->getAddress(),
                        'city' => $store->getCity(),
                        'state' => $store->getState(),
                        'zip_code' => $store->getZipCode(),
                        'phone' => $store->getPhone(),
                        'active' => $store->isActive(),
                        'manager_id' => $store->getManagerId()?->toString(),
                        'unit_id' => $storeUnit->getId()->toString(),
                        'created_at' => $store->getCreatedAt()->format('Y-m-d H:i:s'),
                    ];
                }
            }

            return $this->successResponse([
                'region' => [
                    'id' => $region->getId()->toString(),
                    'name' => $region->getName(),
                    'code' => $region->getCode(),
                ],
                'stores' => $stores,
                'total' => count($stores)
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to list stores by region: ' . $e->getMessage());
            return $this->errorResponse('Failed to list stores', 500);
        }
    }
}