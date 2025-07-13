<?php

declare(strict_types=1);

namespace App\Application\Task\UseCases;

use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Domain\Task\ValueObjects\TaskId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class DeleteTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    public function execute(array $params): void
    {
        $taskId = new TaskId($params['task_id']);
        $userId = $params['user_id'];
        
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            throw new \InvalidArgumentException('Task not found');
        }
        
        // Check permissions - only MASTER, GO, and task creator can delete
        $canDelete = in_array($user->hierarchy_role, ['MASTER', 'GO']) ||
                    $task->getCreatedBy()->getValue() === $userId;
        
        if (!$canDelete) {
            throw new \Exception('Access denied - insufficient permissions to delete task');
        }
        
        // Check if task can be viewed by user (additional security)
        if (!$task->canBeViewedBy(
            $user->hierarchy_role,
            $user->organization_id,
            $this->getUserOrganizationUnitId($user)
        )) {
            throw new \Exception('Access denied');
        }
        
        $this->taskRepository->delete($taskId);
    }
    
    private function getUserOrganizationUnitId(UserModel $user): ?int
    {
        $position = $user->positions()->where('is_active', true)->first();
        return $position?->organization_unit_id;
    }
}