<?php

declare(strict_types=1);

namespace App\Application\Task\UseCases;

use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Domain\Task\ValueObjects\TaskStatus;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class GetKanbanBoardUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    public function execute(array $params): array
    {
        $userId = $params['user_id'];
        $organizationUnitId = $params['organization_unit_id'] ?? null;
        $storeId = $params['store_id'] ?? null;
        $hierarchyFilter = $params['hierarchy_filter'] ?? [];
        
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        // If store_id is provided, get tasks for that specific store
        if ($storeId) {
            return $this->getStoreTasksWithCounts($user, $organizationUnitId, $storeId);
        }
        
        return $this->buildKanbanBoard($user, $organizationUnitId, $hierarchyFilter);
    }
    
    private function buildKanbanBoard(UserModel $user, ?string $organizationUnitId, array $hierarchyFilter): array
    {
        $statuses = ['TODO', 'IN_PROGRESS', 'IN_REVIEW', 'BLOCKED', 'DONE'];
        $board = [];
        
        foreach ($statuses as $status) {
            $tasks = $this->getTasksForStatus($user, $status, $organizationUnitId, $hierarchyFilter);
            
            $board[$status] = [
                'title' => $this->getStatusDisplayName($status),
                'tasks' => $tasks,
                'count' => count($tasks)
            ];
        }
        
        return [
            'board' => $board,
            'user_permissions' => $this->getUserPermissions($user),
            'statistics' => $this->getBoardStatistics($user, $organizationUnitId, $hierarchyFilter)
        ];
    }
    
    private function getTasksForStatus(UserModel $user, string $status, ?string $organizationUnitId, array $hierarchyFilter): array
    {
        $taskStatus = new TaskStatus($status);
        
        // Use hierarchy filter if available
        if (!empty($hierarchyFilter)) {
            $tasks = $this->taskRepository->filterByHierarchy($hierarchyFilter);
            
            // Filter by status
            $tasks = array_filter($tasks, function($task) use ($status) {
                return $task->getStatus()->getValue() === $status;
            });
        } elseif ($organizationUnitId) {
            $unitId = new OrganizationUnitId($organizationUnitId);
            $tasks = $this->taskRepository->findByOrganizationUnit($unitId);
        } else {
            $orgId = new OrganizationId($user->organization_id);
            $tasks = $this->taskRepository->findByStatus($taskStatus, $orgId);
        }
        
        // Filter by user permissions and status
        $filteredTasks = array_filter($tasks, function($task) use ($user, $status) {
            return $task->getStatus()->getValue() === $status &&
                   $task->canBeViewedBy(
                       $user->hierarchy_role,
                       $user->organization_id,
                       $this->getUserOrganizationUnitId($user)
                   );
        });
        
        // Convert to array format
        return array_map(function($task) {
            return [
                'id' => $task->getId()->getValue(),
                'title' => $task->getTitle(),
                'description' => substr($task->getDescription(), 0, 100) . '...',
                'priority' => $task->getPriority()->getValue(),
                'status' => $task->getStatus()->getValue(),
                'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
                'is_overdue' => $task->isOverdue(),
                'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                'assignees' => array_map(fn($userId) => $userId->getValue(), $task->getAssignedUsers()),
                'subtask_count' => 0 // Will be filled by repository if needed
            ];
        }, $filteredTasks);
    }
    
    private function getStatusDisplayName(string $status): string
    {
        return match($status) {
            'TODO' => 'Para Fazer',
            'IN_PROGRESS' => 'Em Progresso',
            'IN_REVIEW' => 'Em Revisão',
            'BLOCKED' => 'Bloqueado',
            'DONE' => 'Concluído',
            default => $status
        };
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
    
    private function getBoardStatistics(UserModel $user, ?string $organizationUnitId, array $hierarchyFilter): array
    {
        $orgId = new OrganizationId($user->organization_id);
        $statusCounts = $this->taskRepository->countByStatus($orgId);
        
        $total = array_sum($statusCounts);
        $completed = $statusCounts['DONE'] ?? 0;
        $inProgress = $statusCounts['IN_PROGRESS'] ?? 0;
        $blocked = $statusCounts['BLOCKED'] ?? 0;
        
        return [
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'in_progress_tasks' => $inProgress,
            'blocked_tasks' => $blocked,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'overdue_tasks' => count($this->taskRepository->findOverdueTasks($orgId))
        ];
    }
    
    private function getUserOrganizationUnitId(UserModel $user): ?int
    {
        $position = $user->positions()->where('is_active', true)->first();
        return $position?->organization_unit_id;
    }
    
    private function getStoreTasksWithCounts(UserModel $user, ?string $organizationUnitId, string $storeId): array
    {
        if (!$organizationUnitId) {
            return [
                'tasks' => [],
                'counts' => [
                    'TODO' => 0,
                    'IN_PROGRESS' => 0,
                    'IN_REVIEW' => 0,
                    'BLOCKED' => 0,
                    'DONE' => 0
                ]
            ];
        }
        
        // Get all tasks for this organization unit
        $unitId = new OrganizationUnitId($organizationUnitId);
        $tasks = $this->taskRepository->findByOrganizationUnit($unitId);
        
        // Filter by user permissions
        $filteredTasks = array_filter($tasks, function($task) use ($user) {
            return $task->canBeViewedBy(
                $user->hierarchy_role,
                $user->organization_id,
                $this->getUserOrganizationUnitId($user)
            );
        });
        
        // Group by status and count
        $counts = [
            'TODO' => 0,
            'IN_PROGRESS' => 0,
            'IN_REVIEW' => 0,
            'BLOCKED' => 0,
            'DONE' => 0
        ];
        
        $tasksByStatus = [];
        foreach ($filteredTasks as $task) {
            $status = $task->getStatus()->getValue();
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            
            $tasksByStatus[] = [
                'id' => $task->getId()->getValue(),
                'title' => $task->getTitle(),
                'description' => substr($task->getDescription(), 0, 100) . '...',
                'priority' => $task->getPriority()->getValue(),
                'status' => $status,
                'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
                'is_overdue' => $task->isOverdue(),
                'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                'assignees' => array_map(fn($userId) => $userId->getValue(), $task->getAssignedUsers()),
                'department_id' => $task->getDepartmentId()?->getValue()
            ];
        }
        
        return [
            'tasks' => $tasksByStatus,
            'counts' => $counts
        ];
    }
}