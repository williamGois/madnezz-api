<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Application\UseCases\CreateOrganization\CreateOrganizationCommand;
use App\Application\UseCases\CreateOrganization\CreateOrganizationUseCase;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Name;
use App\Domain\User\ValueObjects\Password;
use App\Domain\User\ValueObjects\Phone;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\Entities\Department;
use App\Domain\Organization\Entities\Position;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use App\Domain\Organization\Repositories\PositionRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\Organization\ValueObjects\DepartmentId;
use App\Domain\Organization\ValueObjects\PositionId;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrganizationManagementController extends BaseController
{
    public function __construct(
        private readonly CreateOrganizationUseCase $createOrganizationUseCase,
        private readonly OrganizationUnitRepositoryInterface $unitRepository,
        private readonly DepartmentRepositoryInterface $departmentRepository,
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly OrganizationRepositoryInterface $organizationRepository
    ) {}

    /**
     * Create a new organization (MASTER only)
     * 
     * @OA\Post(
     *     path="/api/v1/organizations",
     *     summary="Create a new organization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "code"},
     *             @OA\Property(property="name", type="string", example="Acme Corporation"),
     *             @OA\Property(property="code", type="string", example="ACME"),
     *             @OA\Property(property="go_user", type="object",
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@acme.com"),
     *                 @OA\Property(property="password", type="string", format="password", example="SecurePass123!"),
     *                 @OA\Property(property="phone", type="string", example="+5511999999999")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden - Only MASTER users can create organizations")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->hierarchy_role !== 'MASTER') {
            return $this->errorResponse('Access denied. Only MASTER users can create organizations.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:organizations,code',
            'go_user' => 'sometimes|array',
            'go_user.name' => 'required_with:go_user|string|max:255',
            'go_user.email' => 'required_with:go_user|email|unique:users_ddd,email',
            'go_user.password' => 'required_with:go_user|string|min:8',
            'go_user.phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        DB::beginTransaction();
        
        try {
            // Prepare GO user data if provided
            $goUserData = null;
            if ($request->has('go_user')) {
                $goData = $request->input('go_user');
                $goUserData = [
                    'name' => new Name($goData['name']),
                    'email' => new Email($goData['email']),
                    'password' => new Password($goData['password']),
                    'phone' => isset($goData['phone']) ? new Phone($goData['phone']) : null,
                ];
            }

            // Create organization command
            $command = new CreateOrganizationCommand(
                $request->input('name'),
                $request->input('code'),
                new UserId($user->id),
                $goUserData
            );

            // Execute use case
            $response = $this->createOrganizationUseCase->execute($command);
            
            // Create root organization unit
            $organizationId = new OrganizationId($response->organizationId);
            $rootUnit = OrganizationUnit::create(
                $organizationId,
                'Sede',
                'SEDE',
                'company',
                null // no parent
            );
            $this->unitRepository->save($rootUnit);

            // Create default departments
            $departments = [
                ['name' => 'Administrativo', 'code' => 'ADM'],
                ['name' => 'Financeiro', 'code' => 'FIN'],
                ['name' => 'Comercial', 'code' => 'COM'],
                ['name' => 'Marketing', 'code' => 'MKT'],
                ['name' => 'Operações', 'code' => 'OPS'],
            ];

            foreach ($departments as $dept) {
                $department = Department::create(
                    $organizationId,
                    $dept['name'],
                    $dept['code']
                );
                $this->departmentRepository->save($department);
            }

            // If GO was created, create position linking GO to root unit
            if ($response->goUserId) {
                $position = Position::create(
                    $organizationId,
                    $rootUnit->getId(),
                    new UserId($response->goUserId),
                    DepartmentId::fromString($this->departmentRepository->findByCode($organizationId, 'ADM')->getId()->toString()),
                    'Gestor Organizacional',
                    'GO'
                );
                $this->positionRepository->save($position);
            }

            DB::commit();

            return $this->successResponse([
                'organization' => [
                    'id' => $response->organizationId,
                    'name' => $response->organizationName,
                    'code' => $response->organizationCode,
                ],
                'go_user_id' => $response->goUserId,
                'root_unit_id' => $rootUnit->getId()->toString(),
                'message' => 'Organization created successfully'
            ], 201);

        } catch (\DomainException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create organization: ' . $e->getMessage());
            return $this->errorResponse('Failed to create organization', 500);
        }
    }

    /**
     * List all organizations (MASTER only)
     * 
     * @OA\Get(
     *     path="/api/v1/organizations",
     *     summary="List all organizations",
     *     tags={"Organizations"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="organizations", type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Only MASTER users can list organizations")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->hierarchy_role !== 'MASTER') {
            return $this->errorResponse('Access denied. Only MASTER users can list organizations.', 403);
        }

        try {
            $organizations = $this->organizationRepository->findAll();
            
            $organizationsData = array_map(function($org) {
                return [
                    'id' => $org->getId()->toString(),
                    'name' => $org->getName(),
                    'code' => $org->getCode(),
                    'active' => $org->isActive(),
                    'created_at' => $org->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $org->getUpdatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $organizations);

            return $this->successResponse([
                'organizations' => $organizationsData,
                'total' => count($organizations)
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to list organizations: ' . $e->getMessage());
            return $this->errorResponse('Failed to list organizations', 500);
        }
    }

    /**
     * Update organization details (MASTER only)
     * 
     * @OA\Patch(
     *     path="/api/v1/organizations/{id}",
     *     summary="Update organization name and/or code",
     *     tags={"Organizations"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="New Organization Name"),
     *             @OA\Property(property="code", type="string", example="NEWCODE")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden - Only MASTER users can update organizations"),
     *     @OA\Response(response=404, description="Organization not found")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->hierarchy_role !== 'MASTER') {
            return $this->errorResponse('Access denied. Only MASTER users can update organizations.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        DB::beginTransaction();
        
        try {
            $organizationId = new OrganizationId($id);
            $organization = $this->organizationRepository->findById($organizationId);
            
            if (!$organization) {
                return $this->errorResponse('Organization not found', 404);
            }

            // Check code uniqueness if updating code
            if ($request->has('code') && $request->input('code') !== $organization->getCode()) {
                if ($this->organizationRepository->codeExists($request->input('code'))) {
                    return $this->errorResponse("Organization with code '{$request->input('code')}' already exists", 400);
                }
            }

            // Update organization
            if ($request->has('name')) {
                $organization->updateName($request->input('name'));
            }
            
            if ($request->has('code')) {
                $organization->updateCode($request->input('code'));
            }

            $this->organizationRepository->save($organization);
            
            DB::commit();

            return $this->successResponse([
                'organization' => [
                    'id' => $organization->getId()->toString(),
                    'name' => $organization->getName(),
                    'code' => $organization->getCode(),
                    'active' => $organization->isActive(),
                    'updated_at' => $organization->getUpdatedAt()->format('Y-m-d H:i:s'),
                ],
                'message' => 'Organization updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to update organization: ' . $e->getMessage());
            return $this->errorResponse('Failed to update organization', 500);
        }
    }

    /**
     * Update organization status (MASTER only)
     * 
     * @OA\Patch(
     *     path="/api/v1/organizations/{id}/status",
     *     summary="Activate or deactivate an organization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"active"},
     *             @OA\Property(property="active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=403, description="Forbidden - Only MASTER users can update organization status"),
     *     @OA\Response(response=404, description="Organization not found")
     * )
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->hierarchy_role !== 'MASTER') {
            return $this->errorResponse('Access denied. Only MASTER users can update organization status.', 403);
        }

        $validator = Validator::make($request->all(), [
            'active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        DB::beginTransaction();
        
        try {
            $organizationId = new OrganizationId($id);
            $organization = $this->organizationRepository->findById($organizationId);
            
            if (!$organization) {
                return $this->errorResponse('Organization not found', 404);
            }

            // Update status
            if ($request->input('active')) {
                $organization->activate();
            } else {
                $organization->deactivate();
            }

            $this->organizationRepository->save($organization);
            
            DB::commit();

            return $this->successResponse([
                'organization' => [
                    'id' => $organization->getId()->toString(),
                    'name' => $organization->getName(),
                    'code' => $organization->getCode(),
                    'active' => $organization->isActive(),
                    'updated_at' => $organization->getUpdatedAt()->format('Y-m-d H:i:s'),
                ],
                'message' => 'Organization status updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to update organization status: ' . $e->getMessage());
            return $this->errorResponse('Failed to update organization status', 500);
        }
    }
}