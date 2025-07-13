<?php

declare(strict_types=1);

namespace App\Domain\Task\Entities;

use App\Domain\Task\ValueObjects\TaskId;
use App\Domain\Task\ValueObjects\TaskStatus;
use App\Domain\Task\ValueObjects\TaskPriority;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use DateTime;

class Task
{
    private array $assignedUsers = [];
    private array $watchers = [];
    private array $comments = [];
    private array $attachments = [];
    private ?DateTime $completedAt = null;
    
    public function __construct(
        private TaskId $id,
        private string $title,
        private string $description,
        private TaskStatus $status,
        private TaskPriority $priority,
        private UserId $createdBy,
        private OrganizationId $organizationId,
        private ?OrganizationUnitId $organizationUnitId,
        private ?TaskId $parentTaskId,
        private ?DateTime $dueDate,
        private DateTime $createdAt,
        private DateTime $updatedAt
    ) {}

    public function getId(): TaskId
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function getCreatedBy(): UserId
    {
        return $this->createdBy;
    }

    public function getOrganizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function getOrganizationUnitId(): ?OrganizationUnitId
    {
        return $this->organizationUnitId;
    }

    public function getParentTaskId(): ?TaskId
    {
        return $this->parentTaskId;
    }

    public function getDueDate(): ?DateTime
    {
        return $this->dueDate;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?DateTime
    {
        return $this->completedAt;
    }

    public function getAssignedUsers(): array
    {
        return $this->assignedUsers;
    }

    public function getWatchers(): array
    {
        return $this->watchers;
    }

    public function changeStatus(TaskStatus $newStatus): void
    {
        $this->status = $newStatus;
        $this->updatedAt = new DateTime();
        
        if ($newStatus->isCompleted()) {
            $this->completedAt = new DateTime();
        }
    }

    public function changePriority(TaskPriority $newPriority): void
    {
        $this->priority = $newPriority;
        $this->updatedAt = new DateTime();
    }

    public function updateTitle(string $title): void
    {
        $this->title = $title;
        $this->updatedAt = new DateTime();
    }

    public function updateDescription(string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new DateTime();
    }

    public function assignUser(UserId $userId): void
    {
        if (!in_array($userId, $this->assignedUsers)) {
            $this->assignedUsers[] = $userId;
            $this->updatedAt = new DateTime();
        }
    }

    public function unassignUser(UserId $userId): void
    {
        $this->assignedUsers = array_filter(
            $this->assignedUsers,
            fn($id) => !$id->equals($userId)
        );
        $this->updatedAt = new DateTime();
    }

    public function addWatcher(UserId $userId): void
    {
        if (!in_array($userId, $this->watchers)) {
            $this->watchers[] = $userId;
        }
    }

    public function removeWatcher(UserId $userId): void
    {
        $this->watchers = array_filter(
            $this->watchers,
            fn($id) => !$id->equals($userId)
        );
    }

    public function setDueDate(?DateTime $dueDate): void
    {
        $this->dueDate = $dueDate;
        $this->updatedAt = new DateTime();
    }

    public function isOverdue(): bool
    {
        if (!$this->dueDate || $this->status->isCompleted()) {
            return false;
        }
        
        return $this->dueDate < new DateTime();
    }

    public function canBeViewedBy(string $userRole, int $userOrgId, ?int $userOrgUnitId): bool
    {
        // MASTER can see all tasks
        if ($userRole === 'MASTER') {
            return true;
        }
        
        // Check organization match
        if ($this->organizationId->getValue() !== $userOrgId) {
            return false;
        }
        
        // GO can see all tasks in their organization
        if ($userRole === 'GO') {
            return true;
        }
        
        // GR and STORE_MANAGER need unit match
        if ($this->organizationUnitId && $userOrgUnitId) {
            return $this->organizationUnitId->getValue() === $userOrgUnitId;
        }
        
        return false;
    }
}