<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\User\UseCases\ListUsersUseCase;
use App\Application\User\UseCases\CreateUserUseCase;
use App\Application\User\UseCases\UpdateUserUseCase;
use App\Application\User\UseCases\DeleteUserUseCase;
use App\Application\User\UseCases\BulkUpdateUsersUseCase;
use App\Application\User\UseCases\SearchUsersUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct(
        private ListUsersUseCase $listUsersUseCase,
        private CreateUserUseCase $createUserUseCase,
        private UpdateUserUseCase $updateUserUseCase,
        private DeleteUserUseCase $deleteUserUseCase,
        private BulkUpdateUsersUseCase $bulkUpdateUsersUseCase,
        private SearchUsersUseCase $searchUsersUseCase
    ) {}

    /**
     * List users with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $params = array_merge($request->all(), [
                'user_id' => $request->user()->id
            ]);

            $result = $this->listUsersUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Create a new user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'hierarchy_role' => 'required|in:MASTER,GO,GR,STORE_MANAGER',
            'phone' => 'nullable|string|max:20',
            'organization_id' => 'nullable|uuid',
            'store_id' => 'nullable|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $params = array_merge($validator->validated(), [
                'requesting_user_id' => $request->user()->id
            ]);

            $result = $this->createUserUseCase->execute($params);
            
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get user details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            // For now, use the list use case with specific ID filter
            $params = [
                'user_id' => $request->user()->id,
                'search' => $id,
                'limit' => 1
            ];

            $result = $this->searchUsersUseCase->execute($params);
            
            if (empty($result['data']['users'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data']['users'][0]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'password' => 'sometimes|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'status' => 'sometimes|in:ACTIVE,INACTIVE,SUSPENDED',
            'organization_id' => 'sometimes|uuid',
            'store_id' => 'nullable|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $params = array_merge($validator->validated(), [
                'requesting_user_id' => $request->user()->id,
                'user_id' => $id
            ]);

            $result = $this->updateUserUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete user
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $params = [
                'requesting_user_id' => $request->user()->id,
                'user_id' => $id
            ];

            $result = $this->deleteUserUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Bulk update users
     */
    public function bulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'required|uuid',
            'action' => 'required|in:activate,deactivate,suspend,delete'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $params = array_merge($validator->validated(), [
                'requesting_user_id' => $request->user()->id
            ]);

            $result = $this->bulkUpdateUsersUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Search users
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:100',
            'limit' => 'sometimes|integer|min:1|max:50',
            'role' => 'sometimes|in:MASTER,GO,GR,STORE_MANAGER',
            'status' => 'sometimes|in:ACTIVE,INACTIVE,SUSPENDED',
            'organization_id' => 'sometimes|uuid',
            'store_id' => 'sometimes|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $params = array_merge($validator->validated(), [
                'requesting_user_id' => $request->user()->id
            ]);

            $result = $this->searchUsersUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get filter options for user listing
     */
    public function filterOptions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Build filter options based on user's permissions
            $options = [
                'roles' => [],
                'statuses' => [
                    ['value' => 'ACTIVE', 'label' => 'Ativo'],
                    ['value' => 'INACTIVE', 'label' => 'Inativo'],
                    ['value' => 'SUSPENDED', 'label' => 'Suspenso']
                ],
                'organizations' => [],
                'stores' => []
            ];

            // Add roles based on hierarchy
            switch ($user->hierarchy_role) {
                case 'MASTER':
                    $options['roles'] = [
                        ['value' => 'MASTER', 'label' => 'Master'],
                        ['value' => 'GO', 'label' => 'GO - Gestor Organizacional'],
                        ['value' => 'GR', 'label' => 'GR - Gestor Regional'],
                        ['value' => 'STORE_MANAGER', 'label' => 'Gerente de Loja']
                    ];
                    break;
                    
                case 'GO':
                    $options['roles'] = [
                        ['value' => 'GR', 'label' => 'GR - Gestor Regional'],
                        ['value' => 'STORE_MANAGER', 'label' => 'Gerente de Loja']
                    ];
                    break;
                    
                case 'GR':
                    $options['roles'] = [
                        ['value' => 'STORE_MANAGER', 'label' => 'Gerente de Loja']
                    ];
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $options
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}