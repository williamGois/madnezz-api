<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Application\Task\UseCases\CreateTaskUseCase;
use App\Application\Task\UseCases\UpdateTaskUseCase;
use App\Application\Task\UseCases\GetTasksUseCase;
use App\Application\Task\UseCases\DeleteTaskUseCase;
use App\Application\Task\UseCases\GetKanbanBoardUseCase;
use App\Application\Task\UseCases\GetFilteredTasksUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function __construct(
        private CreateTaskUseCase $createTaskUseCase,
        private UpdateTaskUseCase $updateTaskUseCase,
        private GetTasksUseCase $getTasksUseCase,
        private DeleteTaskUseCase $deleteTaskUseCase,
        private GetKanbanBoardUseCase $getKanbanBoardUseCase,
        private GetFilteredTasksUseCase $getFilteredTasksUseCase
    ) {}

    /**
     * Get tasks for the current user based on their hierarchy
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hierarchyFilter = $request->get('hierarchy_filter', []);
            
            $params = [
                'user_id' => $user->id,
                'status' => $request->get('status'),
                'assigned_to_me' => $request->boolean('assigned_to_me'),
                'created_by_me' => $request->boolean('created_by_me'),
                'hierarchy_filter' => $hierarchyFilter
            ];
            
            $tasks = $this->getTasksUseCase->execute($params);
            
            return response()->json([
                'success' => true,
                'data' => $tasks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Kanban board view
     */
    public function kanbanBoard(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $visibleStoreIds = $request->attributes->get('visible_store_ids', []);
            
            // Build board data for each visible store
            $board = [];
            
            foreach ($visibleStoreIds as $storeId) {
                // Get store information
                $store = \DB::table('stores')->find($storeId);
                if (!$store) {
                    continue;
                }
                
                // Get organization unit for this store
                $storeUnit = \DB::table('organization_units')
                    ->where('organization_id', $store->organization_id)
                    ->where('code', $store->code)
                    ->where('type', 'store')
                    ->first();
                
                if (!$storeUnit) {
                    continue;
                }
                
                // Get tasks for this store
                $params = [
                    'user_id' => $user->id,
                    'organization_unit_id' => $storeUnit->id,
                    'store_id' => $storeId
                ];
                
                $storeTasks = $this->getKanbanBoardUseCase->execute($params);
                
                // Add store column to board
                $board[] = [
                    'store_id' => $storeId,
                    'store_name' => $store->name,
                    'store_code' => $store->code,
                    'tasks' => $storeTasks['tasks'] ?? [],
                    'counts' => $storeTasks['counts'] ?? [
                        'TODO' => 0,
                        'IN_PROGRESS' => 0,
                        'IN_REVIEW' => 0,
                        'BLOCKED' => 0,
                        'DONE' => 0
                    ]
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'board' => $board,
                    'total_stores' => count($board)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new task
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'priority' => 'required|in:LOW,MEDIUM,HIGH,URGENT',
                'organization_unit_id' => 'nullable|exists:organization_units,id',
                'department_id' => 'nullable|exists:departments,id',
                'parent_task_id' => 'nullable|exists:tasks,id',
                'due_date' => 'nullable|date',
                'assigned_users' => 'nullable|array',
                'assigned_users.*' => 'exists:users,id'
            ]);
            
            $user = Auth::user();
            $orgContext = $request->get('organization_context');
            
            $task = $this->createTaskUseCase->execute([
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'created_by' => $user->id,
                'organization_id' => $user->organization_id,
                'organization_unit_id' => $request->organization_unit_id,
                'department_id' => $request->department_id,
                'parent_task_id' => $request->parent_task_id,
                'due_date' => $request->due_date,
                'assigned_users' => $request->assigned_users ?? [],
                'organization_context' => $orgContext
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $task,
                'message' => 'Task created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific task
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $task = $this->getTasksUseCase->execute([
                'user_id' => $user->id,
                'task_id' => $id
            ]);
            
            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found or access denied'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a task
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'status' => 'sometimes|in:TODO,IN_PROGRESS,IN_REVIEW,BLOCKED,DONE,CANCELLED',
                'priority' => 'sometimes|in:LOW,MEDIUM,HIGH,URGENT',
                'due_date' => 'nullable|date',
                'assigned_users' => 'nullable|array',
                'assigned_users.*' => 'exists:users,id'
            ]);
            
            $user = Auth::user();
            
            $task = $this->updateTaskUseCase->execute([
                'task_id' => $id,
                'user_id' => $user->id,
                'updates' => $request->only([
                    'title', 'description', 'status', 'priority', 'due_date', 'assigned_users'
                ])
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $task,
                'message' => 'Task updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a task
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $this->deleteTaskUseCase->execute([
                'task_id' => $id,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filtered tasks with advanced filtering and caching
     */
    public function filtered(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $hierarchyFilter = $request->get('hierarchy_filter', []);
            
            $params = array_merge($request->all(), [
                'user_id' => $user->id,
                'hierarchy_filter' => $hierarchyFilter
            ]);
            
            $result = $this->getFilteredTasksUseCase->execute($params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options for dropdowns
     */
    public function filterOptions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'statuses' => [
                        'TODO' => 'Para Fazer',
                        'IN_PROGRESS' => 'Em Progresso',
                        'IN_REVIEW' => 'Em Revisão',
                        'BLOCKED' => 'Bloqueado',
                        'DONE' => 'Concluído'
                    ],
                    'priorities' => [
                        'LOW' => 'Baixa',
                        'MEDIUM' => 'Média',
                        'HIGH' => 'Alta',
                        'CRITICAL' => 'Crítica'
                    ],
                    'departments' => [
                        'administrative' => 'Administrativo',
                        'financial' => 'Financeiro',
                        'marketing' => 'Marketing',
                        'operations' => 'Operações',
                        'trade' => 'Comercial',
                        'macro' => 'Macro'
                    ],
                    'user_permissions' => [
                        'can_create' => true,
                        'can_edit_all' => in_array($user->hierarchy_role, ['MASTER', 'GO']),
                        'can_delete' => in_array($user->hierarchy_role, ['MASTER', 'GO']),
                        'can_assign_users' => in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR']),
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