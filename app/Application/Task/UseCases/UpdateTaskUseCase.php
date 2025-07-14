<?php

declare(strict_types=1);

namespace App\Application\Task\UseCases;

use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Domain\Task\ValueObjects\TaskId;
use App\Domain\Task\ValueObjects\TaskStatus;
use App\Domain\Task\ValueObjects\TaskPriority;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use DateTime;

class UpdateTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    public function execute(array $params): array
    {
        $taskId = new TaskId($params['task_id']);
        $userId = $params['user_id'];
        $updates = $params['updates'];
        
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            throw new \InvalidArgumentException('Task not found');
        }
        
        // Check permissions
        if (!$task->canBeViewedBy(
            $user->hierarchy_role,
            $user->organization_id,
            $this->getUserOrganizationUnitId($user)
        )) {
            throw new \Exception('Access denied');
        }
        
        // Apply updates
        if (isset($updates['title'])) {
            $task->updateTitle($updates['title']);
        }
        
        if (isset($updates['description'])) {
            $task->updateDescription($updates['description']);
        }
        
        if (isset($updates['status'])) {
            $newStatus = new TaskStatus($updates['status']);
            if ($task->getStatus()->canTransitionTo($newStatus)) {
                $task->changeStatus($newStatus);
            } else {
                throw new \Exception("Invalid status transition from {$task->getStatus()->getValue()} to {$newStatus->getValue()}");
            }
        }
        
        if (isset($updates['priority'])) {
            $task->changePriority(new TaskPriority($updates['priority']));
        }
        
        if (isset($updates['due_date'])) {
            $dueDate = $updates['due_date'] ? new DateTime($updates['due_date']) : null;
            $task->setDueDate($dueDate);
        }
        
        if (isset($updates['assigned_users']) && is_array($updates['assigned_users'])) {
            // Remove all current assignees
            foreach ($task->getAssignedUsers() as $assignedUserId) {
                $task->unassignUser($assignedUserId);
            }
            
            // Add new assignees
            foreach ($updates['assigned_users'] as $newUserId) {
                $task->assignUser(new UserId($newUserId));
            }
        }
        
        $this->taskRepository->save($task);
        
        // Invalidar cache após atualizar tarefa
        Cache::tags(['tasks'])->flush();
        
        return [
            'id' => $task->getId()->getValue(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus()->getValue(),
            'priority' => $task->getPriority()->getValue(),
            'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
            'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $task->getUpdatedAt()->format('Y-m-d H:i:s'),
            'assigned_users' => array_map(fn($userId) => $userId->getValue(), $task->getAssignedUsers())
        ];
    }
    
    private function getUserOrganizationUnitId(UserModel $user): ?int
    {
        $position = $user->positions()->where('is_active', true)->first();
        return $position?->organization_unit_id;
    }
}