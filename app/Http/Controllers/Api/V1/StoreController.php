<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Application\Store\UseCases\CreateStoreUseCase;
use App\Application\Store\UseCases\ListStoresUseCase;
use App\Application\Store\UseCases\UpdateStoreUseCase;
use App\Application\Store\UseCases\DeleteStoreUseCase;
use App\Application\Store\UseCases\AssignManagerUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class StoreController extends Controller
{
    public function __construct(
        private CreateStoreUseCase $createStoreUseCase,
        private ListStoresUseCase $listStoresUseCase,
        private UpdateStoreUseCase $updateStoreUseCase,
        private DeleteStoreUseCase $deleteStoreUseCase,
        private AssignManagerUseCase $assignManagerUseCase
    ) {}

    /**
     * Lista lojas com filtros hierárquicos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            $params = array_merge($request->all(), [
                'user_id' => $user->id
            ]);
            
            $result = $this->listStoresUseCase->execute($params);
            
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \InvalidArgumentException ? 400 : 500);
        }
    }

    /**
     * Cria uma nova loja
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            $params = array_merge($request->all(), [
                'user_id' => $user->id
            ]);
            
            $result = $this->createStoreUseCase->execute($params);
            
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \InvalidArgumentException ? 400 : 500);
        }
    }

    /**
     * Exibe uma loja específica
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            $params = [
                'user_id' => $user->id,
                'store_id' => $id
            ];
            
            $result = $this->listStoresUseCase->execute(array_merge($params, [
                'limit' => 1,
                'search' => null
            ]));
            
            if (empty($result['data']['stores'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $result['data']['stores'][0]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma loja
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            $params = array_merge($request->all(), [
                'user_id' => $user->id,
                'store_id' => $id
            ]);
            
            $result = $this->updateStoreUseCase->execute($params);
            
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \InvalidArgumentException ? 400 : 500);
        }
    }

    /**
     * Remove uma loja
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            $params = [
                'user_id' => $user->id,
                'store_id' => $id
            ];
            
            $result = $this->deleteStoreUseCase->execute($params);
            
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \InvalidArgumentException ? 400 : 500);
        }
    }

    /**
     * Atribui/remove gerente de uma loja
     */
    public function assignManager(Request $request, string $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            $params = array_merge($request->all(), [
                'user_id' => $user->id,
                'store_id' => $id
            ]);
            
            $result = $this->assignManagerUseCase->execute($params);
            
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \InvalidArgumentException ? 400 : 500);
        }
    }

    /**
     * Lista opções para filtros (dropdown data)
     */
    public function filterOptions(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            // Buscar organizações, regiões e departamentos baseado na hierarquia do usuário
            $organizations = [];
            $regions = [];
            $departments = ['administrative', 'financial', 'marketing', 'operations', 'trade', 'macro'];
            $managers = [];
            
            // TODO: Implementar busca real baseada na hierarquia
            
            return response()->json([
                'success' => true,
                'data' => [
                    'organizations' => $organizations,
                    'regions' => $regions,
                    'departments' => $departments,
                    'managers' => $managers,
                    'user_permissions' => [
                        'can_create' => in_array($user->hierarchy_role, ['MASTER', 'GO']),
                        'can_edit' => in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR']),
                        'can_delete' => in_array($user->hierarchy_role, ['MASTER', 'GO']),
                        'hierarchy_role' => $user->hierarchy_role
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}