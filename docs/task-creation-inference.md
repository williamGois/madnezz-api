# Task Creation with Inference Documentation

## Overview

The task creation system now includes intelligent inference of `department_id` and `organization_unit_id` based on the user's position and hierarchy role, along with hierarchical scope validation.

## Inference Logic

### Department ID Inference

1. **Explicit Value**: If `department_id` is provided in the request, it is used directly
2. **Single Department**: If the user's position has only one department, it is automatically used
3. **Multiple Departments**: If the user has multiple departments, `department_id` must be explicitly provided
4. **No Department**: Tasks can be created without a department

### Organization Unit ID Inference

1. **Explicit Value**: If `organization_unit_id` is provided in the request, it is used directly
2. **Organization Context**: Uses `organization_unit_id` from the organization context middleware
3. **Store Manager**: Always uses their store's organization unit ID
4. **Other Roles**: Organization unit is optional (can create organization-wide tasks)

## Hierarchical Scope Validation

### MASTER Users
- Can create tasks anywhere in any organization
- No restrictions on unit or department selection

### GO (Gestor Organizacional)
- Can create tasks in any unit within their organization
- Cannot create tasks in units outside their organization
- Can assign any department they have access to

### GR (Gerente Regional)
- Can create tasks in their region
- Can create tasks in any store within their region
- Cannot create tasks in other regions or parent units
- Limited to departments they have access to

### Store Manager
- Can only create tasks in their own store
- Cannot create tasks in other stores or regions
- Limited to departments they have access to

## API Request Examples

### Basic Task Creation (with inference)
```json
POST /api/v1/tasks
{
    "title": "Task Title",
    "description": "Task description",
    "priority": "MEDIUM"
}
```
The system will infer:
- `organization_unit_id` from user's position
- `department_id` if user has single department

### Explicit Department and Unit
```json
POST /api/v1/tasks
{
    "title": "Task Title",
    "description": "Task description",
    "priority": "HIGH",
    "organization_unit_id": "unit-uuid",
    "department_id": "dept-uuid"
}
```

### Task with Assignees
```json
POST /api/v1/tasks
{
    "title": "Task Title",
    "description": "Task description",
    "priority": "LOW",
    "assigned_users": ["user-uuid-1", "user-uuid-2"],
    "due_date": "2025-02-01 10:00:00"
}
```

## Validation Rules

### Department Access
- Users can only create tasks for departments they have access to
- Access is determined by position_departments table
- MASTER users have access to all departments

### Assignee Validation
- Assignees must belong to the same organization (except for MASTER)
- Assignee must exist in the system
- Future enhancement: validate assignee has access to the task's unit/department

### Unit Hierarchy
- Tasks can only be created in units the user has access to
- GR users have access to their region and all child stores
- Validation traverses the unit hierarchy to verify access

## Error Messages

### Invalid Unit Access
```json
{
    "success": false,
    "message": "Cannot create task in unit outside your organization"
}
```

### Invalid Department Access
```json
{
    "success": false,
    "message": "Cannot create task for department you do not have access to"
}
```

### Store Manager Restriction
```json
{
    "success": false,
    "message": "Store managers can only create tasks in their own store"
}
```

### GR Region Restriction
```json
{
    "success": false,
    "message": "Cannot create task outside your region"
}
```

## Implementation Details

### CreateTaskUseCase Methods

#### inferOrganizationUnitId()
1. Checks explicit parameter first
2. Uses organization context if available
3. Special handling for Store Managers
4. Returns null for optional cases

#### inferDepartmentId()
1. Checks explicit parameter first
2. Auto-selects if user has single department
3. Returns null if multiple or no departments

#### validateHierarchicalScope()
1. Validates unit belongs to user's scope
2. Checks department access permissions
3. Enforces role-specific restrictions
4. Throws DomainException on violations

#### validateAssignee()
1. Verifies assignee exists
2. Checks organization membership
3. Future: unit/department access validation

## Testing

The `CreateTaskWithInferenceTest` class covers:
- Department inference scenarios
- Organization unit inference
- Hierarchical access validation
- Error cases and restrictions
- Explicit value overrides

Run tests:
```bash
php artisan test tests/Feature/Task/CreateTaskWithInferenceTest.php
```

## Benefits

1. **User Experience**: Reduces form fields for common cases
2. **Data Consistency**: Ensures tasks are properly scoped
3. **Security**: Enforces hierarchical access control
4. **Flexibility**: Allows explicit overrides when needed
5. **Audit Trail**: Tasks automatically tagged with correct unit/department