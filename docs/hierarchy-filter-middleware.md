# HierarchyFilterMiddleware Documentation

## Overview

The HierarchyFilterMiddleware automatically injects hierarchy-based filters into requests based on the user's role and organizational position. This ensures that users only see data relevant to their organizational scope without requiring explicit filtering in every request.

## How It Works

### Middleware Flow

1. **Prerequisites**: 
   - User must be authenticated (jwt.auth)
   - OrganizationContextMiddleware must run first (org.context)
   
2. **Filter Injection**:
   - Retrieves organization context from request
   - Applies role-specific filters
   - Injects filters into request as `hierarchy_filter`

### Filter Rules by Role

#### MASTER Users
```php
'hierarchy_filter' => [
    'hierarchy_role' => 'MASTER',
    'filters' => []  // No filters - sees everything
]
```

#### GO (Gestor Organizacional) Users
```php
'hierarchy_filter' => [
    'hierarchy_role' => 'GO',
    'filters' => [
        'organization_id' => $orgContext['organization_id']
    ]
]
```

#### GR (Gerente Regional) Users
```php
'hierarchy_filter' => [
    'hierarchy_role' => 'GR',
    'filters' => [
        'organization_unit_id' => $orgContext['organization_unit_id'],
        'include_child_units' => true,
        'child_unit_ids' => [...],  // All stores in the region
        'all_unit_ids' => [...]     // Region ID + all child store IDs
    ]
]
```

#### Store Manager Users
```php
'hierarchy_filter' => [
    'hierarchy_role' => 'STORE_MANAGER',
    'filters' => [
        'organization_unit_id' => $orgContext['organization_unit_id'],
        'store_id' => $orgContext['store_id']
    ]
]
```

## Integration with Controllers

### TaskController Integration

The TaskController automatically receives and uses hierarchy filters:

```php
public function index(Request $request): JsonResponse
{
    $hierarchyFilter = $request->get('hierarchy_filter', []);
    
    $params = [
        'user_id' => $user->id,
        'hierarchy_filter' => $hierarchyFilter,
        // ... other params
    ];
    
    $tasks = $this->getTasksUseCase->execute($params);
}
```

### Repository Implementation

The EloquentTaskRepository implements `filterByHierarchy()`:

```php
public function filterByHierarchy(array $hierarchyFilter): array
{
    $query = TaskModel::with(['assignees', 'subtasks']);
    
    if (empty($hierarchyFilter['filters'])) {
        // MASTER user - no filters
        $models = $query->orderBy('created_at', 'desc')->get();
    } else {
        $filters = $hierarchyFilter['filters'];
        
        // Apply organization filter
        if (isset($filters['organization_id'])) {
            $query->byOrganization($filters['organization_id']);
        }
        
        // Apply unit filters with child unit support
        if (isset($filters['organization_unit_id'])) {
            if ($filters['include_child_units'] ?? false) {
                // GR sees region + all stores
                $unitIds = $filters['all_unit_ids'];
                $query->whereIn('organization_unit_id', $unitIds);
            } else {
                // Store Manager sees only their unit
                $query->where('organization_unit_id', $filters['organization_unit_id']);
            }
        }
    }
    
    return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
}
```

## Route Configuration

Apply the middleware to routes that need hierarchy filtering:

```php
Route::middleware(['jwt.auth', 'org.context', 'hierarchy.filter'])->group(function () {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/filtered', [TaskController::class, 'filtered']);
    Route::get('/tasks/kanban', [TaskController::class, 'kanbanBoard']);
});
```

## Child Unit Resolution

For GR users, the middleware automatically finds all child units (stores):

```php
private function getChildUnitIds(string $parentUnitId): array
{
    // Recursively gets all child units
    $childUnits = DB::table('organization_units')
        ->where('parent_id', $parentUnitId)
        ->where('active', true)
        ->pluck('id')
        ->toArray();
    
    // Continues recursively for multi-level hierarchies
    foreach ($childUnits as $childId) {
        $grandChildren = $this->getChildUnitIds($childId);
        $allChildUnits = array_merge($allChildUnits, $grandChildren);
    }
    
    return array_unique($allChildUnits);
}
```

## Testing

Run the middleware tests:
```bash
php artisan test tests/Feature/Middleware/HierarchyFilterMiddlewareTest.php
```

## Example Scenarios

### Scenario 1: GO User Viewing Tasks
- User: GO of Organization ABC
- Filter Applied: `organization_id = 'abc-123'`
- Result: Sees all tasks in Organization ABC

### Scenario 2: GR User Viewing Kanban Board
- User: GR of South Region
- Filter Applied: `organization_unit_id IN ('south-region-id', 'store-1-id', 'store-2-id')`
- Result: Sees tasks from South Region and all its stores

### Scenario 3: Store Manager Viewing Tasks
- User: Store Manager of Store XYZ
- Filter Applied: `organization_unit_id = 'store-xyz-id'`
- Result: Sees only tasks from Store XYZ

## Performance Considerations

1. **Child Unit Caching**: Consider caching child unit lookups for GR users
2. **Query Optimization**: Use database indexes on organization_unit_id
3. **Batch Loading**: Load related data efficiently with eager loading

## Security Notes

- Middleware enforces data isolation at the application level
- Combined with domain entity permission checks for defense in depth
- Hierarchy filters cannot be bypassed by request manipulation