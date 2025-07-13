<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Examples of hierarchy access control routes
 * These routes demonstrate how to use the organizational hierarchy middleware
 */

Route::prefix('api/v1')->group(function () {
    
    // Routes that require authentication and organization context
    Route::middleware(['jwt.auth', 'org.context'])->group(function () {
        
        // Get current user's organization context
        Route::get('/organization/context', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->get('organization_context')
            ]);
        });
        
        // GO level routes - only GO can access
        Route::middleware(['hierarchy.access:go'])->group(function () {
            Route::get('/organization/dashboard', function (Request $request) {
                return response()->json([
                    'success' => true,
                    'message' => 'GO dashboard - organization overview',
                    'context' => $request->get('organization_context')
                ]);
            });
            
            Route::get('/organization/all-units', function (Request $request) {
                return response()->json([
                    'success' => true,
                    'message' => 'All organization units (GO access)',
                    'units' => 'GO can see all units in organization'
                ]);
            });
        });
        
        // GR level routes - GR and above can access
        Route::middleware(['hierarchy.access:gr'])->group(function () {
            Route::get('/regional/dashboard', function (Request $request) {
                return response()->json([
                    'success' => true,
                    'message' => 'Regional dashboard - regional overview',
                    'context' => $request->get('organization_context')
                ]);
            });
            
            Route::get('/regional/stores', function (Request $request) {
                return response()->json([
                    'success' => true,
                    'message' => 'Stores under regional management',
                    'access_level' => 'GR or GO can access'
                ]);
            });
        });
        
        // Store level routes - all levels can access
        Route::middleware(['hierarchy.access:store_manager'])->group(function () {
            Route::get('/store/dashboard', function (Request $request) {
                return response()->json([
                    'success' => true,
                    'message' => 'Store dashboard - store specific data',
                    'context' => $request->get('organization_context')
                ]);
            });
        });
        
        // Department specific routes
        Route::middleware(['hierarchy.access:,administrative'])->group(function () {
            Route::get('/reports/administrative', function (Request $request) {
                return response()->json([
                    'success' => true,
                    'message' => 'Administrative department reports',
                    'department' => 'administrative'
                ]);
            });
        });
        
        Route::middleware(['hierarchy.access:,financial'])->group(function () {
            Route::get('/reports/financial', function (Request $request) {
                return response()->json([
                    'success' => true,
                    'message' => 'Financial department reports',
                    'department' => 'financial'
                ]);
            });
        });
        
        // Store specific routes with dynamic store_id
        Route::get('/store/{store_id}/details', function (Request $request, string $storeId) {
            return response()->json([
                'success' => true,
                'message' => "Store details for store: {$storeId}",
                'store_id' => $storeId,
                'context' => $request->get('organization_context')
            ]);
        })->middleware(['hierarchy.access']);
        
        // Organization unit specific routes
        Route::get('/unit/{unit_id}/details', function (Request $request, string $unitId) {
            return response()->json([
                'success' => true,
                'message' => "Unit details for: {$unitId}",
                'unit_id' => $unitId,
                'context' => $request->get('organization_context')
            ]);
        })->middleware(['hierarchy.access']);
        
        // Combined hierarchy and department access
        Route::middleware(['hierarchy.access:gr,marketing'])->group(function () {
            Route::get('/campaigns/regional', function (Request $request) {
                return response()->json([
                    'success' => true,
                    'message' => 'Regional marketing campaigns (GR+ with Marketing dept)',
                    'access' => 'GR or GO level with Marketing department access'
                ]);
            });
        });
    });
});

/**
 * Usage Examples:
 * 
 * 1. Basic hierarchy access:
 *    Route::middleware(['hierarchy.access:go']) - Only GO level
 *    Route::middleware(['hierarchy.access:gr']) - GR level and above
 *    Route::middleware(['hierarchy.access:store_manager']) - All levels
 * 
 * 2. Department access:
 *    Route::middleware(['hierarchy.access:,administrative']) - Administrative dept only
 *    Route::middleware(['hierarchy.access:,financial']) - Financial dept only
 * 
 * 3. Combined access:
 *    Route::middleware(['hierarchy.access:gr,marketing']) - GR+ with Marketing
 * 
 * 4. Resource-specific access (automatic via route parameters):
 *    Route::get('/store/{store_id}/...') - Auto-checks store access
 *    Route::get('/unit/{unit_id}/...') - Auto-checks unit access
 * 
 * 5. Organization context injection:
 *    All routes with 'org.context' middleware get organization_context in request
 *    Contains: organization_id, organization_unit_id, position_level, departments, etc.
 */