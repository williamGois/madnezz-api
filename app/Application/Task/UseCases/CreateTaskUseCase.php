<?php

declare(strict_types=1);

namespace App\Application\Task\UseCases;

use App\Domain\Task\Entities\Task;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Domain\Task\ValueObjects\TaskId;
use App\Domain\Task\ValueObjects\TaskStatus;
use App\Domain\Task\ValueObjects\TaskPriority;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use DateTime;

class CreateTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    public function execute(array $params): array
    {
        $task = new Task(
            TaskId::generate(),
            $params['title'],
            $params['description'],
            TaskStatus::todo(),
            new TaskPriority($params['priority']),
            new UserId($params['created_by']),
            new OrganizationId($params['organization_id']),
            $params['organization_unit_id'] ? new OrganizationUnitId($params['organization_unit_id']) : null,
            $params['parent_task_id'] ? new TaskId($params['parent_task_id']) : null,
            $params['due_date'] ? new DateTime($params['due_date']) : null,
            new DateTime(),
            new DateTime()
        );
        
        // Assign users if provided
        if (!empty($params['assigned_users'])) {
            foreach ($params['assigned_users'] as $userId) {
                $task->assignUser(new UserId($userId));
            }
        }
        
        $this->taskRepository->save($task);
        
        return [
            'id' => $task->getId()->getValue(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus()->getValue(),
            'priority' => $task->getPriority()->getValue(),
            'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
            'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
            'assigned_users' => array_map(fn($userId) => $userId->getValue(), $task->getAssignedUsers())
        ];
    }
}