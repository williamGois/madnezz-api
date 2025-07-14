<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Application\Organization\UseCases\CreateRegion\CreateRegionCommand;
use App\Application\Organization\UseCases\CreateRegion\CreateRegionUseCase;
use App\Application\User\UseCases\CreateUser\CreateUserCommand;
use App\Application\User\UseCases\CreateUser\CreateUserUseCase;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\Organization\Repositories\PositionRepositoryInterface;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\Organization\ValueObjects\DepartmentId;
use App\Domain\Organization\Entities\Position;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name as UserName;
use App\Domain\User\ValueObjects\Password;
use App\Domain\User\ValueObjects\Phone;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Middleware\OrganizationContextMiddleware;

class RegionController extends BaseController
{
    public function __construct(
        private readonly CreateRegionUseCase $createRegionUseCase,
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly OrganizationUnitRepositoryInterface $unitRepository,
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly DepartmentRepositoryInterface $departmentRepository,
        private readonly HierarchicalUserRepositoryInterface $userRepository
    ) {}

    /**
     * Create a new region (GO or MASTER only)
     * 
     * @OA\Post(
     *     path="/api/v1/organizations/{org_id}/regions",
     *     summary="Create a new region",
     *     tags={"Regions"},
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
     *             required={"name", "code"},
     *             @OA\Property(property="name", type="string", example="Região Sul"),
     *             @OA\Property(property="code", type="string", example="RS")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Region created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden - Only MASTER or GO users can create regions")
     * )
     */
    public function store(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || (!in_array($user->hierarchy_role, ['MASTER', 'GO']))) {
            return $this->errorResponse('Access denied. Only MASTER or GO users can create regions.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $command = new CreateRegionCommand(
                new OrganizationId($orgId),
                $request->input('name'),
                $request->input('code'),
                new UserId($user->id)
            );

            $response = $this->createRegionUseCase->execute($command);

            return $this->successResponse([
                'region' => [
                    'id' => $response->regionId,
                    'name' => $response->name,
                    'code' => $response->code,
                    'organization_id' => $response->organizationId,
                ],
                'message' => 'Region created successfully'
            ], 201);

        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            \Log::error('Failed to create region: ' . $e->getMessage());
            return $this->errorResponse('Failed to create region', 500);
        }
    }

    /**
     * List regions (GO or MASTER only)
     * 
     * @OA\Get(
     *     path="/api/v1/organizations/{org_id}/regions",
     *     summary="List all regions in an organization",
     *     tags={"Regions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="org_id",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
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
    public function index(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || (!in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR']))) {
            return $this->errorResponse('Access denied.', 403);
        }

        try {
            $organizationId = new OrganizationId($orgId);
            
            // If GO or GR, verify they belong to this organization
            if (in_array($user->hierarchy_role, ['GO', 'GR']) && $user->organization_id !== $orgId) {
                return $this->errorResponse('Access denied to this organization.', 403);
            }

            $regions = $this->unitRepository->findByType($organizationId, 'regional');
            
            $regionsData = array_map(function($region) {
                return [
                    'id' => $region->getId()->toString(),
                    'name' => $region->getName(),
                    'code' => $region->getCode(),
                    'active' => $region->isActive(),
                    'created_at' => $region->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $regions);

            return $this->successResponse([
                'regions' => $regionsData,
                'total' => count($regions)
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to list regions: ' . $e->getMessage());
            return $this->errorResponse('Failed to list regions', 500);
        }
    }

    /**
     * Create a Regional Manager (GR) for a region (GO or MASTER only)
     * 
     * @OA\Post(
     *     path="/api/v1/organizations/{org_id}/regions/{region_id}/gr",
     *     summary="Create a Regional Manager (GR) for a region",
     *     tags={"Regions"},
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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password"},
     *             @OA\Property(property="name", type="string", example="João Regional"),
     *             @OA\Property(property="email", type="string", format="email", example="gr@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePass123!"),
     *             @OA\Property(property="phone", type="string", example="+5511999999999")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Regional Manager created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden - Only MASTER or GO users can create GR")
     * )
     */
    public function createRegionalManager(Request $request, string $orgId, string $regionId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || (!in_array($user->hierarchy_role, ['MASTER', 'GO']))) {
            return $this->errorResponse('Access denied. Only MASTER or GO users can create Regional Managers.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users_ddd,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        DB::beginTransaction();
        
        try {
            $organizationId = new OrganizationId($orgId);
            $regionUnitId = new OrganizationUnitId($regionId);
            
            // Verify region exists and is of type regional
            $region = $this->unitRepository->findById($regionUnitId);
            if (!$region || $region->getType() !== 'regional') {
                return $this->errorResponse('Region not found', 404);
            }
            
            // Verify region belongs to the organization
            if (!$region->getOrganizationId()->equals($organizationId)) {
                return $this->errorResponse('Region does not belong to this organization', 400);
            }

            // If GO, verify they belong to this organization
            if ($user->hierarchy_role === 'GO' && $user->organization_id !== $orgId) {
                return $this->errorResponse('GO can only create GR in their own organization', 403);
            }

            // Create GR user
            $grUser = HierarchicalUser::createGR(
                new UserName($request->input('name')),
                new Email($request->input('email')),
                new Password($request->input('password')),
                $organizationId,
                $request->input('phone')
            );

            $this->userRepository->save($grUser);

            // Get administrative department
            $adminDept = $this->departmentRepository->findByCode($organizationId, 'ADM');
            if (!$adminDept) {
                throw new \DomainException('Administrative department not found');
            }

            // Create position linking GR to the region
            $position = Position::create(
                $organizationId,
                $regionUnitId,
                $grUser->getId(),
                $adminDept->getId(),
                'Gerente Regional',
                'GR'
            );
            $this->positionRepository->save($position);

            // Clear cache for the new GR user
            OrganizationContextMiddleware::clearCacheForUser($grUser->getId()->toString());

            DB::commit();

            return $this->successResponse([
                'user' => [
                    'id' => $grUser->getId()->toString(),
                    'name' => $grUser->getName()->getValue(),
                    'email' => $grUser->getEmail()->getValue(),
                    'hierarchy_role' => 'GR',
                ],
                'region' => [
                    'id' => $region->getId()->toString(),
                    'name' => $region->getName(),
                    'code' => $region->getCode(),
                ],
                'position_id' => $position->getId()->toString(),
                'message' => 'Regional Manager created successfully'
            ], 201);

        } catch (\DomainException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create Regional Manager: ' . $e->getMessage());
            return $this->errorResponse('Failed to create Regional Manager', 500);
        }
    }
}