<?php

declare(strict_types=1);

namespace App\Application\Task\UseCases;

use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Task\Eloquent\TaskModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetFilteredTasksUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    public function execute(array $params): array
    {
        $userId = $params['user_id'];
        $user = UserModel::find($userId);
        
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        // Extrair parâmetros
        $filters = $this->extractFilters($params);
        $pagination = $this->extractPagination($params);
        $view = $params['view'] ?? 'list'; // 'list' ou 'kanban'
        
        // Cache inteligente com TTL adaptativo
        $cacheKey = $this->generateCacheKey($user, $filters, $pagination, $view);
        $ttl = $this->calculateCacheTTL($cacheKey);
        
        return Cache::tags(['tasks', 'filtered-tasks'])->remember($cacheKey, $ttl, function () use ($user, $filters, $pagination, $view) {
            if ($view === 'kanban') {
                return $this->buildKanbanView($user, $filters);
            }
            return $this->buildListView($user, $filters, $pagination);
        });
    }
    
    private function extractFilters(array $params): array
    {
        return [
            'status' => $params['status'] ?? null,
            'priority' => $params['priority'] ?? null,
            'assigned_to' => $params['assigned_to'] ?? null,
            'created_by' => $params['created_by'] ?? null,
            'store_id' => $params['store_id'] ?? null,
            'department_type' => $params['department_type'] ?? null,
            'organization_id' => $params['organization_id'] ?? null,
            'parent_unit_id' => $params['parent_unit_id'] ?? null,
            'search' => $params['search'] ?? null,
            'due_date_from' => $params['due_date_from'] ?? null,
            'due_date_to' => $params['due_date_to'] ?? null,
            'created_from' => $params['created_from'] ?? null,
            'created_to' => $params['created_to'] ?? null,
            'tags' => $params['tags'] ?? null,
            'overdue' => $params['overdue'] ?? null,
            'completed' => $params['completed'] ?? null
        ];
    }
    
    private function extractPagination(array $params): array
    {
        $sortDirection = $params['sort_direction'] ?? 'desc';
        return [
            'page' => max(1, intval($params['page'] ?? 1)),
            'limit' => min(100, max(1, intval($params['limit'] ?? 20))),
            'sort_by' => $params['sort_by'] ?? 'created_at',
            'sort_direction' => in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc'
        ];
    }
    
    private function generateCacheKey(UserModel $user, array $filters, array $pagination, string $view): string
    {
        $keyData = [
            'user_id' => $user->id,
            'user_role' => $user->hierarchy_role,
            'user_org' => $user->organization_id,
            'filters' => array_filter($filters),
            'pagination' => $pagination,
            'view' => $view
        ];
        
        return 'filtered_tasks:' . md5(json_encode($keyData));
    }
    
    private function calculateCacheTTL(string $cacheKey): int
    {
        $popularityKey = "popularity:{$cacheKey}";
        $hitCount = Cache::get($popularityKey, 0);
        Cache::put($popularityKey, $hitCount + 1, 3600);
        
        // TTL adaptativo baseado em popularidade e tipo de dados
        if ($hitCount > 20) {
            return 1800; // 30 minutos para consultas muito populares
        } elseif ($hitCount > 5) {
            return 900;  // 15 minutos para consultas populares
        }
        
        return 300; // 5 minutos para consultas novas
    }
    
    private function buildListView(UserModel $user, array $filters, array $pagination): array
    {
        $query = TaskModel::with([
            'creator:id,name,email',
            'assignees:id,name,email',
            'organizationUnit:id,name,type',
            'organization:id,name'
        ]);
        
        // Aplicar filtros de segurança hierárquica
        $query = $this->applyHierarchicalFilters($query, $user);
        
        // Aplicar filtros específicos
        $query = $this->applyCustomFilters($query, $filters);
        
        // Aplicar ordenação
        $query = $this->applySorting($query, $pagination);
        
        // Contar total para paginação
        $total = $query->count();
        
        // Aplicar paginação usando cursor para melhor performance
        $offset = ($pagination['page'] - 1) * $pagination['limit'];
        $tasks = $query->offset($offset)->limit($pagination['limit'])->get();
        
        return [
            'success' => true,
            'data' => [
                'tasks' => $this->formatTasks($tasks),
                'pagination' => [
                    'current_page' => $pagination['page'],
                    'per_page' => $pagination['limit'],
                    'total' => $total,
                    'total_pages' => ceil($total / $pagination['limit']),
                    'has_next' => ($pagination['page'] * $pagination['limit']) < $total,
                    'has_prev' => $pagination['page'] > 1
                ],
                'filters_applied' => array_filter($filters),
                'statistics' => $this->getFilterStatistics($user, $filters),
                'user_permissions' => $this->getUserPermissions($user)
            ]
        ];
    }
    
    private function buildKanbanView(UserModel $user, array $filters): array
    {
        $query = TaskModel::with([
            'creator:id,name,email',
            'assignees:id,name,email',
            'organizationUnit:id,name,type',
            'organization:id,name'
        ]);
        
        // Aplicar filtros de segurança hierárquica
        $query = $this->applyHierarchicalFilters($query, $user);
        
        // Aplicar filtros específicos (exceto status)
        $filtersWithoutStatus = $filters;
        unset($filtersWithoutStatus['status']);
        $query = $this->applyCustomFilters($query, $filtersWithoutStatus);
        
        // Agrupar por status
        $tasksByStatus = $query->get()->groupBy('status');
        
        $board = [
            'TODO' => ['title' => 'Para Fazer', 'tasks' => [], 'count' => 0],
            'IN_PROGRESS' => ['title' => 'Em Progresso', 'tasks' => [], 'count' => 0],
            'IN_REVIEW' => ['title' => 'Em Revisão', 'tasks' => [], 'count' => 0],
            'BLOCKED' => ['title' => 'Bloqueado', 'tasks' => [], 'count' => 0],
            'DONE' => ['title' => 'Concluído', 'tasks' => [], 'count' => 0]
        ];
        
        foreach ($tasksByStatus as $status => $tasks) {
            if (isset($board[$status])) {
                $board[$status]['tasks'] = $this->formatTasks($tasks);
                $board[$status]['count'] = $tasks->count();
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'board' => $board,
                'filters_applied' => array_filter($filters),
                'statistics' => $this->getKanbanStatistics($tasksByStatus),
                'user_permissions' => $this->getUserPermissions($user)
            ]
        ];
    }
    
    private function applyHierarchicalFilters($query, UserModel $user)
    {
        switch ($user->hierarchy_role) {
            case 'MASTER':
                // MASTER vê todas as tarefas
                break;
                
            case 'GO':
                // GO vê tarefas da sua organização
                $query->where('organization_id', $user->organization_id);
                break;
                
            case 'GR':
                // GR vê tarefas da sua região
                $position = $user->positions()->where('is_active', true)->first();
                if ($position) {
                    $regionId = $position->organization_unit_id;
                    $query->where(function ($q) use ($regionId) {
                        $q->where('organization_unit_id', $regionId)
                          ->orWhereHas('organizationUnit', function ($subQ) use ($regionId) {
                              $subQ->where('parent_id', $regionId);
                          });
                    });
                }
                break;
                
            case 'STORE_MANAGER':
                // STORE_MANAGER vê apenas tarefas da sua loja
                $position = $user->positions()->where('is_active', true)->first();
                if ($position) {
                    $query->where('organization_unit_id', $position->organization_unit_id);
                }
                break;
        }
        
        return $query;
    }
    
    private function applyCustomFilters($query, array $filters)
    {
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }
        
        if ($filters['priority']) {
            $query->where('priority', $filters['priority']);
        }
        
        if ($filters['assigned_to']) {
            $query->whereHas('assignees', function ($q) use ($filters) {
                $q->where('user_id', $filters['assigned_to']);
            });
        }
        
        if ($filters['created_by']) {
            $query->where('created_by', $filters['created_by']);
        }
        
        if ($filters['store_id']) {
            $query->where('organization_unit_id', $filters['store_id']);
        }
        
        if ($filters['organization_id']) {
            $query->where('organization_id', $filters['organization_id']);
        }
        
        if ($filters['parent_unit_id']) {
            $query->whereHas('organizationUnit', function ($q) use ($filters) {
                $q->where('parent_id', $filters['parent_unit_id']);
            });
        }
        
        if ($filters['department_type']) {
            $query->whereHas('departments', function ($q) use ($filters) {
                $q->where('type', $filters['department_type']);
            });
        }
        
        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%")
                  ->orWhere('tags', 'ILIKE', "%{$search}%");
            });
        }
        
        if ($filters['due_date_from']) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }
        
        if ($filters['due_date_to']) {
            $query->where('due_date', '<=', $filters['due_date_to']);
        }
        
        if ($filters['created_from']) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        
        if ($filters['created_to']) {
            $query->where('created_at', '<=', $filters['created_to']);
        }
        
        if ($filters['tags']) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->where('tags', 'ILIKE', "%{$tag}%");
                }
            });
        }
        
        if ($filters['overdue']) {
            $query->where('due_date', '<', now())
                  ->whereNotIn('status', ['DONE']);
        }
        
        if (isset($filters['completed'])) {
            if ($filters['completed']) {
                $query->where('status', 'DONE');
            } else {
                $query->whereNotIn('status', ['DONE']);
            }
        }
        
        return $query;
    }
    
    private function applySorting($query, array $pagination)
    {
        $sortBy = $pagination['sort_by'];
        $direction = $pagination['sort_direction'];
        
        $allowedSortFields = [
            'title' => 'title',
            'priority' => 'priority',
            'status' => 'status',
            'due_date' => 'due_date',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at'
        ];
        
        if (isset($allowedSortFields[$sortBy])) {
            $query->orderBy($allowedSortFields[$sortBy], $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        $query->orderBy('id', 'desc');
        
        return $query;
    }
    
    private function formatTasks($tasks): array
    {
        return $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toISOString(),
                'tags' => $task->tags ? explode(',', $task->tags) : [],
                'creator' => $task->creator ? [
                    'id' => $task->creator->id,
                    'name' => $task->creator->name,
                    'email' => $task->creator->email
                ] : null,
                'assignees' => $task->assignees->map(function ($assignee) {
                    return [
                        'id' => $assignee->id,
                        'name' => $assignee->name,
                        'email' => $assignee->email
                    ];
                })->toArray(),
                'organization_unit' => $task->organizationUnit ? [
                    'id' => $task->organizationUnit->id,
                    'name' => $task->organizationUnit->name,
                    'type' => $task->organizationUnit->type
                ] : null,
                'organization' => $task->organization ? [
                    'id' => $task->organization->id,
                    'name' => $task->organization->name
                ] : null,
                'is_overdue' => $task->due_date && $task->due_date < now() && $task->status !== 'DONE',
                'created_at' => $task->created_at->toISOString(),
                'updated_at' => $task->updated_at->toISOString()
            ];
        })->toArray();
    }
    
    private function getFilterStatistics(UserModel $user, array $filters): array
    {
        $baseQuery = TaskModel::query();
        $baseQuery = $this->applyHierarchicalFilters($baseQuery, $user);
        $baseQuery = $this->applyCustomFilters($baseQuery, $filters);
        
        return [
            'total_tasks' => $baseQuery->count(),
            'by_status' => $baseQuery->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_priority' => $baseQuery->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
            'overdue_count' => (clone $baseQuery)->where('due_date', '<', now())
                ->whereNotIn('status', ['DONE'])
                ->count()
        ];
    }
    
    private function getKanbanStatistics($tasksByStatus): array
    {
        $total = $tasksByStatus->flatten()->count();
        $completed = $tasksByStatus->get('DONE', collect())->count();
        
        return [
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'in_progress_tasks' => $tasksByStatus->get('IN_PROGRESS', collect())->count(),
            'blocked_tasks' => $tasksByStatus->get('BLOCKED', collect())->count(),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'overdue_tasks' => $tasksByStatus->flatten()->filter(function ($task) {
                return $task->due_date && $task->due_date < now() && $task->status !== 'DONE';
            })->count()
        ];
    }
    
    private function getUserPermissions(UserModel $user): array
    {
        return [
            'can_create' => true,
            'can_edit_all' => in_array($user->hierarchy_role, ['MASTER', 'GO']),
            'can_delete' => in_array($user->hierarchy_role, ['MASTER', 'GO']),
            'can_assign_users' => in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR']),
            'hierarchy_role' => $user->hierarchy_role
        ];
    }
}