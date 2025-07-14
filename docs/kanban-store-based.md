# Kanban Board - Store-Based View Documentation

## Overview

The Kanban board now displays tasks organized by store rather than by status. This provides a better view of task distribution across different locations in the organization hierarchy.

## Middleware Chain

The `/api/v1/tasks/kanban` endpoint uses the following middleware chain:

1. **jwt.auth** - Authenticates the user
2. **org.context** - Injects organization context into the request
3. **hierarchy.filter** - Adds hierarchy-based filters
4. **visible.stores** - Determines which stores the user can see
5. **cache.kanban** - Caches the response for performance

## Visible Stores Logic

### MASTER Users
- Can see all stores across all organizations
- No filtering applied

### GO (Gestor Organizacional)
- Can see all stores within their organization
- Filtered by organization_id

### GR (Gerente Regional)
- Can see all stores within their region
- Based on their active position's organization_unit_id
- Includes all child stores of their regional unit

### Store Manager
- Can only see their own store
- Based on their active position's organization_unit_id

## API Response Format

### Request
```http
GET /api/v1/tasks/kanban
Authorization: Bearer {token}
```

### Response Structure
```json
{
    "success": true,
    "data": {
        "board": [
            {
                "store_id": "uuid-store-1",
                "store_name": "Loja Centro",
                "store_code": "STORE001",
                "tasks": [
                    {
                        "id": "task-uuid",
                        "title": "Task Title",
                        "description": "Task description...",
                        "priority": "HIGH",
                        "status": "IN_PROGRESS",
                        "due_date": "2025-02-01 10:00:00",
                        "is_overdue": false,
                        "created_at": "2025-01-15 09:00:00",
                        "assignees": ["user-uuid-1", "user-uuid-2"],
                        "department_id": "dept-uuid"
                    }
                ],
                "counts": {
                    "TODO": 5,
                    "IN_PROGRESS": 3,
                    "IN_REVIEW": 2,
                    "BLOCKED": 1,
                    "DONE": 10
                }
            },
            {
                "store_id": "uuid-store-2",
                "store_name": "Loja Shopping",
                "store_code": "STORE002",
                "tasks": [],
                "counts": {
                    "TODO": 0,
                    "IN_PROGRESS": 0,
                    "IN_REVIEW": 0,
                    "BLOCKED": 0,
                    "DONE": 0
                }
            }
        ],
        "total_stores": 2
    }
}
```

## Task Status Values

- **TODO** - Task not started
- **IN_PROGRESS** - Task being worked on
- **IN_REVIEW** - Task awaiting review
- **BLOCKED** - Task blocked by dependency
- **DONE** - Task completed

## Cache Behavior

The Kanban board response is cached with the following characteristics:

- **Cache Key**: Includes user ID, organization ID, and request parameters
- **TTL**: 5 minutes for frequently accessed boards, up to 1 hour for less frequent
- **Invalidation**: Cache is cleared when tasks are created, updated, or deleted
- **Tags**: `tasks`, `kanban` for targeted cache flushing

## Performance Considerations

1. **Lazy Loading**: Tasks are loaded per store to avoid memory issues
2. **Pagination**: For stores with many tasks, consider implementing pagination
3. **Filtering**: Apply status filters on the client-side for better UX
4. **Real-time Updates**: Consider WebSocket integration for live updates

## Frontend Implementation Tips

### Kanban Board Layout
```javascript
// Example React component structure
const KanbanBoard = ({ data }) => {
    return (
        <div className="kanban-board">
            {data.board.map(store => (
                <StoreColumn 
                    key={store.store_id}
                    store={store}
                    onTaskMove={handleTaskMove}
                />
            ))}
        </div>
    );
};
```

### Filtering by Status
```javascript
// Client-side status filtering
const filterTasksByStatus = (tasks, status) => {
    return tasks.filter(task => task.status === status);
};
```

### Store Column Component
```javascript
const StoreColumn = ({ store }) => {
    const statuses = ['TODO', 'IN_PROGRESS', 'IN_REVIEW', 'BLOCKED', 'DONE'];
    
    return (
        <div className="store-column">
            <h3>{store.store_name}</h3>
            {statuses.map(status => (
                <StatusSection
                    key={status}
                    status={status}
                    tasks={filterTasksByStatus(store.tasks, status)}
                    count={store.counts[status]}
                />
            ))}
        </div>
    );
};
```

## Error Handling

### No Visible Stores
```json
{
    "success": true,
    "data": {
        "board": [],
        "total_stores": 0
    }
}
```

### Invalid Permissions
```json
{
    "success": false,
    "message": "Insufficient permissions to view kanban board"
}
```

## Migration from Status-Based View

If migrating from the old status-based Kanban view:

1. Update frontend components to handle store-based structure
2. Implement client-side grouping by status within each store
3. Add store selector/filter UI component
4. Update drag-and-drop logic to handle store context

## Security Notes

1. Store visibility is enforced at the middleware level
2. Tasks are filtered based on user's hierarchical permissions
3. No cross-organization data leakage is possible
4. Cache keys include user context to prevent data mixing