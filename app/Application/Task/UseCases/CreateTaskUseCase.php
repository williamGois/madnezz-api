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
use App\Domain\Organization\ValueObjects\DepartmentId;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use DateTime;

class CreateTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private OrganizationUnitRepositoryInterface $unitRepository,
        private DepartmentRepositoryInterface $departmentRepository
    ) {}

    public function execute(array $params): array
    {
        $userId = $params['created_by'];
        $orgContext = $params['organization_context'] ?? null;
        
        // Get user to validate permissions
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        // Infer organization_unit_id if not provided
        $organizationUnitId = $this->inferOrganizationUnitId($params, $orgContext, $user);
        
        // Infer department_id if not provided
        $departmentId = $this->inferDepartmentId($params, $orgContext, $user);
        
        // Validate hierarchical scope
        $this->validateHierarchicalScope($user, $organizationUnitId, $departmentId, $orgContext);
        
        // Create the task
        $task = new Task(
            TaskId::generate(),
            $params['title'],
            $params['description'],
            TaskStatus::todo(),
            new TaskPriority($params['priority']),
            new UserId($params['created_by']),
            new OrganizationId($params['organization_id']),
            $organizationUnitId,
            $departmentId,
            $params['parent_task_id'] ? new TaskId($params['parent_task_id']) : null,
            $params['due_date'] ? new DateTime($params['due_date']) : null,
            new DateTime(),
            new DateTime()
        );
        
        // Assign users if provided
        if (!empty($params['assigned_users'])) {
            foreach ($params['assigned_users'] as $assigneeId) {
                // Validate assignee can be assigned to this task
                $this->validateAssignee($assigneeId, $organizationUnitId, $user);
                $task->assignUser(new UserId($assigneeId));
            }
        }
        
        $this->taskRepository->save($task);
        
        // Invalidar cache após criar nova tarefa
        Cache::tags(['tasks'])->flush();
        
        return [
            'id' => $task->getId()->getValue(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus()->getValue(),
            'priority' => $task->getPriority()->getValue(),
            'organization_unit_id' => $organizationUnitId?->getValue(),
            'department_id' => $departmentId?->getValue(),
            'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
            'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
            'assigned_users' => array_map(fn($userId) => $userId->getValue(), $task->getAssignedUsers())
        ];
    }
    
    private function inferOrganizationUnitId(array $params, ?array $orgContext, UserModel $user): ?OrganizationUnitId
    {
        // If explicitly provided, use it
        if (!empty($params['organization_unit_id'])) {
            return new OrganizationUnitId($params['organization_unit_id']);
        }
        
        // If organization context has unit_id, use it
        if ($orgContext && !empty($orgContext['organization_unit_id'])) {
            return new OrganizationUnitId($orgContext['organization_unit_id']);
        }
        
        // For Store Manager, always use their store
        if ($user->hierarchy_role === 'STORE_MANAGER' && $orgContext && !empty($orgContext['store_id'])) {
            // Get the organization unit ID from the store
            $store = DB::table('stores')->where('id', $orgContext['store_id'])->first();
            if ($store) {
                $unit = DB::table('organization_units')
                    ->where('organization_id', $store->organization_id)
                    ->where('code', $store->code)
                    ->where('type', 'store')
                    ->first();
                if ($unit) {
                    return new OrganizationUnitId($unit->id);
                }
            }
        }
        
        // For other roles, the unit is optional
        return null;
    }
    
    private function inferDepartmentId(array $params, ?array $orgContext, UserModel $user): ?DepartmentId
    {
        // If explicitly provided, use it
        if (!empty($params['department_id'])) {
            return new DepartmentId($params['department_id']);
        }
        
        // Try to infer from user's active position
        if ($orgContext && !empty($orgContext['departments']) && count($orgContext['departments']) === 1) {
            // If user has only one department, use it
            $department = $orgContext['departments'][0];
            if (isset($department['id'])) {
                return new DepartmentId($department['id']);
            }
        }
        
        // Department is optional
        return null;
    }
    
    private function validateHierarchicalScope(UserModel $user, ?OrganizationUnitId $unitId, ?DepartmentId $departmentId, ?array $orgContext): void
    {
        // MASTER can create tasks anywhere
        if ($user->hierarchy_role === 'MASTER') {
            return;
        }
        
        // GO can create tasks in any unit of their organization
        if ($user->hierarchy_role === 'GO') {
            if ($unitId) {
                $unit = $this->unitRepository->findById($unitId);
                if (!$unit || $unit->getOrganizationId()->getValue() !== $user->organization_id) {
                    throw new \DomainException('Cannot create task in unit outside your organization');
                }
            }
            return;
        }
        
        // GR can create tasks in their region or child stores
        if ($user->hierarchy_role === 'GR' && $unitId) {
            $unit = $this->unitRepository->findById($unitId);
            if (!$unit) {
                throw new \DomainException('Invalid organization unit');
            }
            
            // Check if unit is the GR's region or a child of it
            if ($orgContext && isset($orgContext['organization_unit_id'])) {
                $grUnitId = $orgContext['organization_unit_id'];
                
                // Allow if it's their own unit
                if ($unit->getId()->getValue() === $grUnitId) {
                    return;
                }
                
                // Check if it's a child unit
                $parent = $unit->getParentId();
                while ($parent) {
                    if ($parent->getValue() === $grUnitId) {
                        return; // It's a child of GR's region
                    }
                    // Get next parent
                    $parentUnit = $this->unitRepository->findById($parent);
                    $parent = $parentUnit ? $parentUnit->getParentId() : null;
                }
                
                throw new \DomainException('Cannot create task outside your region');
            }
        }
        
        // Store Manager can only create tasks in their store
        if ($user->hierarchy_role === 'STORE_MANAGER' && $unitId) {
            if ($orgContext && isset($orgContext['organization_unit_id'])) {
                if ($unitId->getValue() !== $orgContext['organization_unit_id']) {
                    throw new \DomainException('Store managers can only create tasks in their own store');
                }
            }
        }
        
        // Validate department access if provided
        if ($departmentId && $orgContext && isset($orgContext['departments'])) {
            $userDeptIds = array_column($orgContext['departments'], 'id');
            if (!in_array($departmentId->getValue(), $userDeptIds)) {
                throw new \DomainException('Cannot create task for department you do not have access to');
            }
        }
    }
    
    private function validateAssignee(string $assigneeId, ?OrganizationUnitId $unitId, UserModel $creator): void
    {
        $assignee = UserModel::find($assigneeId);
        if (!$assignee) {
            throw new \InvalidArgumentException("Assignee with ID {$assigneeId} not found");
        }
        
        // Check if assignee belongs to the same organization
        if ($assignee->organization_id !== $creator->organization_id && $creator->hierarchy_role !== 'MASTER') {
            throw new \DomainException('Cannot assign task to user from different organization');
        }
        
        // Additional validation could be added here based on business rules
    }
}