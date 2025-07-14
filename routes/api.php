<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\MasterController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\HierarchyController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\Api\EnterpriseController;

// Health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
        'timestamp' => now()->toIso8601String()
    ]);
});

Route::middleware('jwt.auth')->get('/user', function (Request $request) {
    return $request->user();
});

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    
    // Authentication routes (using DDD architecture)
    Route::prefix('auth')->group(function () {
        // Public routes
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        
        // Protected routes
        Route::middleware('jwt.auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
        });
    });
    
    // Protected API routes
    Route::middleware('jwt.auth')->group(function () {
        // User management (admin only)
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('users', UserController::class);
        });
        
        // MASTER routes - test access without org context
        Route::prefix('master')->group(function () {
            Route::get('/test-access', [MasterController::class, 'testAccess']);
            Route::get('/organizations', [MasterController::class, 'listOrganizations']);
            Route::get('/stores', [MasterController::class, 'listStores']);
        });
        
        // Organization management (MASTER only)
        Route::middleware('role:MASTER')->group(function () {
            Route::get('/organizations', [\App\Http\Controllers\Api\V1\OrganizationManagementController::class, 'index']);
            Route::post('/organizations', [\App\Http\Controllers\Api\V1\OrganizationManagementController::class, 'store']);
            Route::patch('/organizations/{id}', [\App\Http\Controllers\Api\V1\OrganizationManagementController::class, 'update']);
            Route::patch('/organizations/{id}/status', [\App\Http\Controllers\Api\V1\OrganizationManagementController::class, 'updateStatus']);
        });
        
        // Region management (GO or MASTER)
        Route::post('/organizations/{org_id}/regions', [RegionController::class, 'store']);
        Route::get('/organizations/{org_id}/regions', [RegionController::class, 'index']);
        Route::post('/organizations/{org_id}/regions/{region_id}/gr', [RegionController::class, 'createRegionalManager']);
        
        // Store management (GO or MASTER)
        Route::post('/organizations/{org_id}/stores', [\App\Http\Controllers\Api\V1\StoreManagementController::class, 'store']);
        Route::get('/organizations/{org_id}/regions/{region_id}/stores', [\App\Http\Controllers\Api\V1\StoreManagementController::class, 'listByRegion']);
    });
    
    // MASTER routes with organization context
    Route::middleware(['jwt.auth', 'org.context'])->group(function () {
        Route::get('/master/dashboard', [MasterController::class, 'dashboard']);
    });
    
    // Organizational hierarchy routes
    Route::middleware(['jwt.auth', 'org.context'])->group(function () {
        
        // Organization context
        Route::get('/organization/context', [OrganizationController::class, 'getContext']);
        
        // GO level routes - only GO can access
        Route::middleware(['hierarchy.access:go'])->group(function () {
            Route::get('/organization/dashboard', [OrganizationController::class, 'goDashboard']);
        });
        
        // GR level routes - GR and above can access
        Route::middleware(['hierarchy.access:gr'])->group(function () {
            Route::get('/regional/dashboard', [OrganizationController::class, 'regionalDashboard']);
            
            // Combined hierarchy and department access
            Route::middleware(['hierarchy.access:gr,marketing'])->group(function () {
                Route::get('/campaigns/regional', [OrganizationController::class, 'regionalCampaigns']);
            });
        });
        
        // Store level routes - all levels can access
        Route::middleware(['hierarchy.access:store_manager'])->group(function () {
            Route::get('/store/dashboard', [OrganizationController::class, 'storeDashboard']);
        });
        
        // Department specific routes
        Route::middleware(['hierarchy.access:,administrative'])->group(function () {
            Route::get('/reports/administrative', [OrganizationController::class, 'administrativeReports']);
        });
        
        Route::middleware(['hierarchy.access:,financial'])->group(function () {
            Route::get('/reports/financial', [OrganizationController::class, 'financialReports']);
        });
        
        // Resource-specific routes with dynamic parameters
        Route::get('/store/{store_id}/details', [OrganizationController::class, 'storeDetails'])
            ->middleware(['hierarchy.access']);
        
        Route::get('/unit/{unit_id}/details', [OrganizationController::class, 'unitDetails'])
            ->middleware(['hierarchy.access']);
            
        // Hierarchy and Statistics routes
        Route::get('/hierarchy', [HierarchyController::class, 'getHierarchy']);
        Route::get('/hierarchy/me', [HierarchyController::class, 'getMyHierarchy'])
            ->middleware(['org.context'])
            ->name('hierarchy.me');
        Route::get('/hierarchy/statistics', [HierarchyController::class, 'getStatistics']);
        Route::get('/hierarchy/organization/{organizationId}', [HierarchyController::class, 'getOrganizationDetails']);
        Route::get('/hierarchy/region/{regionId}/stores', [HierarchyController::class, 'getStoresByRegion']);
        Route::get('/hierarchy/users', [HierarchyController::class, 'getUsersByHierarchy']);
        
        // Task Management (Kanban) routes
        Route::middleware(['hierarchy.filter'])->group(function () {
            Route::get('/tasks', [TaskController::class, 'index']);
            Route::get('/tasks/filtered', [TaskController::class, 'filtered']);
        });
        
        // Kanban route with full middleware chain
        Route::get('/tasks/kanban', [TaskController::class, 'kanbanBoard'])
            ->middleware(['org.context', 'hierarchy.filter', 'visible.stores', 'cache.kanban'])
            ->name('tasks.kanban');
        Route::get('/tasks/filter-options', [TaskController::class, 'filterOptions']);
        Route::post('/tasks', [TaskController::class, 'store'])
            ->middleware(['org.context']);
        Route::get('/tasks/{id}', [TaskController::class, 'show']);
        Route::put('/tasks/{id}', [TaskController::class, 'update']);
        Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
        
        // Enterprise Management routes
        Route::get('/enterprises', [EnterpriseController::class, 'index']);
        Route::get('/enterprises/dropdown', [EnterpriseController::class, 'dropdown']);
        Route::post('/enterprises', [EnterpriseController::class, 'store']);
        Route::get('/enterprises/{id}', [EnterpriseController::class, 'show']);
        Route::put('/enterprises/{id}', [EnterpriseController::class, 'update']);
        Route::delete('/enterprises/{id}', [EnterpriseController::class, 'destroy']);
        
        // Store Management routes
        Route::get('/stores', [App\Http\Controllers\Api\V1\StoreController::class, 'index']);
        Route::get('/stores/filter-options', [App\Http\Controllers\Api\V1\StoreController::class, 'filterOptions']);
        Route::post('/stores', [App\Http\Controllers\Api\V1\StoreController::class, 'store']);
        Route::get('/stores/{id}', [App\Http\Controllers\Api\V1\StoreController::class, 'show']);
        Route::put('/stores/{id}', [App\Http\Controllers\Api\V1\StoreController::class, 'update']);
        Route::delete('/stores/{id}', [App\Http\Controllers\Api\V1\StoreController::class, 'destroy']);
        Route::post('/stores/{id}/assign-manager', [App\Http\Controllers\Api\V1\StoreController::class, 'assignManager']);
        
        // User Management routes
        Route::get('/users', [ApiUserController::class, 'index']);
        Route::get('/users/search', [ApiUserController::class, 'search']);
        Route::get('/users/filter-options', [ApiUserController::class, 'filterOptions']);
        Route::post('/users', [ApiUserController::class, 'store']);
        Route::post('/users/bulk', [ApiUserController::class, 'bulk']);
        Route::get('/users/{id}', [ApiUserController::class, 'show']);
        Route::put('/users/{id}', [ApiUserController::class, 'update']);
        Route::delete('/users/{id}', [ApiUserController::class, 'destroy']);
    });
});