<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Enterprise\UseCases\CreateEnterpriseUseCase;
use App\Application\Enterprise\UseCases\UpdateEnterpriseUseCase;
use App\Application\Enterprise\UseCases\ListEnterprisesUseCase;
use App\Application\Enterprise\UseCases\GetEnterpriseDetailsUseCase;
use App\Application\Enterprise\UseCases\DeleteEnterpriseUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnterpriseController extends Controller
{
    public function __construct(
        private CreateEnterpriseUseCase $createEnterpriseUseCase,
        private UpdateEnterpriseUseCase $updateEnterpriseUseCase,
        private ListEnterprisesUseCase $listEnterprisesUseCase,
        private GetEnterpriseDetailsUseCase $getEnterpriseDetailsUseCase,
        private DeleteEnterpriseUseCase $deleteEnterpriseUseCase
    ) {}
    
    /**
     * List all enterprises with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $params = array_merge($request->all(), [
                'requesting_user_id' => auth()->id()
            ]);
            
            $result = $this->listEnterprisesUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Create a new enterprise
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'code' => 'nullable|string|min:2|max:20|unique:enterprises,code',
            'organization_id' => 'required|uuid|exists:organizations,id',
            'description' => 'nullable|string|max:1000',
            'observations' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);
        
        try {
            $params = array_merge($validated, [
                'requesting_user_id' => auth()->id()
            ]);
            
            $result = $this->createEnterpriseUseCase->execute($params);
            
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Get enterprise details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $params = [
                'enterprise_id' => $id,
                'requesting_user_id' => auth()->id()
            ];
            
            $result = $this->getEnterpriseDetailsUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Update enterprise
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'observations' => 'nullable|string',
            'status' => 'sometimes|in:ACTIVE,INACTIVE,UNDER_CONSTRUCTION,SUSPENDED',
            'metadata' => 'nullable|array',
        ]);
        
        try {
            $params = array_merge($validated, [
                'enterprise_id' => $id,
                'requesting_user_id' => auth()->id()
            ]);
            
            $result = $this->updateEnterpriseUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Delete enterprise
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $params = [
                'enterprise_id' => $id,
                'requesting_user_id' => auth()->id()
            ];
            
            $result = $this->deleteEnterpriseUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Get enterprises for dropdown/select
     */
    public function dropdown(Request $request): JsonResponse
    {
        try {
            $params = [
                'requesting_user_id' => auth()->id(),
                'organization_id' => $request->get('organization_id'),
                'status' => 'ACTIVE',
            ];
            
            $result = $this->listEnterprisesUseCase->execute($params);
            
            // Simplify response for dropdown
            $enterprises = array_map(function($enterprise) {
                return [
                    'id' => $enterprise['id'],
                    'name' => $enterprise['name'],
                    'code' => $enterprise['code'],
                ];
            }, $result['data']['enterprises']);
            
            return response()->json([
                'success' => true,
                'data' => $enterprises
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}