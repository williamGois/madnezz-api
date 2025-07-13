<?php

declare(strict_types=1);

namespace App\Application\Task\UseCases;

use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Domain\Task\ValueObjects\TaskId;
use App\Domain\Task\ValueObjects\TaskStatus;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class GetTasksUseCase
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
        
        // If requesting a specific task
        if (isset($params['task_id'])) {
            $task = $this->taskRepository->findById(new TaskId($params['task_id']));
            
            if (!$task || !$task->canBeViewedBy(
                $user->hierarchy_role,
                $user->organization_id,
                $this->getUserOrganizationUnitId($user)
            )) {
                return null;
            }
            
            return $this->formatTask($task);
        }
        
        // Get tasks based on filters
        if ($params['assigned_to_me'] ?? false) {
            $tasks = $this->taskRepository->findByAssignedUser(new UserId($userId));
        } elseif ($params['created_by_me'] ?? false) {
            $tasks = $this->taskRepository->findByCreator(new UserId($userId));
        } elseif ($params['status'] ?? null) {
            $orgId = new OrganizationId($user->organization_id);
            $tasks = $this->taskRepository->findByStatus(new TaskStatus($params['status']), $orgId);
        } else {
            $orgId = new OrganizationId($user->organization_id);
            $tasks = $this->taskRepository->findByOrganization($orgId);
        }
        
        // Filter by user permissions
        $filteredTasks = array_filter($tasks, function($task) use ($user) {
            return $task->canBeViewedBy(
                $user->hierarchy_role,
                $user->organization_id,
                $this->getUserOrganizationUnitId($user)
            );
        });
        
        return array_map([$this, 'formatTask'], $filteredTasks);
    }
    
    private function formatTask($task): array
    {
        return [
            'id' => $task->getId()->getValue(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus()->getValue(),
            'priority' => $task->getPriority()->getValue(),
            'created_by' => $task->getCreatedBy()->getValue(),
            'organization_id' => $task->getOrganizationId()->getValue(),
            'organization_unit_id' => $task->getOrganizationUnitId()?->getValue(),
            'parent_task_id' => $task->getParentTaskId()?->getValue(),
            'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
            'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $task->getUpdatedAt()->format('Y-m-d H:i:s'),
            'is_overdue' => $task->isOverdue(),
            'assigned_users' => array_map(fn($userId) => $userId->getValue(), $task->getAssignedUsers())
        ];
    }
    
    private function getUserOrganizationUnitId(UserModel $user): ?int
    {
        $position = $user->positions()->where('is_active', true)->first();
        return $position?->organization_unit_id;
    }
}