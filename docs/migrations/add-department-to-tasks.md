# Add Department ID to Tasks Migration

## Overview

This migration adds a `department_id` column to the `tasks` table to allow tasks to be associated with specific departments within an organization.

## Migration Details

### File Location
- Migration file: `/tmp/add_department_id_to_tasks_table.php`
- Should be moved to: `database/migrations/2025_01_14_000001_add_department_id_to_tasks_table.php`

### Schema Changes

#### Up Migration
```php
Schema::table('tasks', function (Blueprint $table) {
    // Add department_id column as UUID
    $table->uuid('department_id')->nullable()->after('organization_unit_id');
    
    // Add foreign key constraint
    $table->foreign('department_id')
        ->references('id')
        ->on('departments')
        ->onDelete('set null');
    
    // Add index for better query performance
    $table->index('department_id');
});
```

#### Down Migration
```php
Schema::table('tasks', function (Blueprint $table) {
    // Drop foreign key constraint first
    $table->dropForeign(['department_id']);
    
    // Drop index
    $table->dropIndex(['department_id']);
    
    // Drop the column
    $table->dropColumn('department_id');
});
```

## Code Updates

### 1. TaskModel (Eloquent)
- Added `department_id` to `$fillable` array
- Added `department()` relationship method:
  ```php
  public function department(): BelongsTo
  {
      return $this->belongsTo(DepartmentModel::class, 'department_id');
  }
  ```

### 2. Task Entity (Domain)
- Added `DepartmentId` value object import
- Added `?DepartmentId $departmentId` to constructor
- Added `getDepartmentId()` getter method

### 3. TaskMapper
- Updated `toDomain()` to map department_id
- Updated `toEloquent()` to include department_id

### 4. CreateTaskUseCase
- Added support for `department_id` parameter
- Creates `DepartmentId` value object when provided

## Running the Migration

1. **Move the migration file**:
   ```bash
   sudo mv /tmp/add_department_id_to_tasks_table.php /home/goisw/madnezz/madnezz-api/database/migrations/2025_01_14_000001_add_department_id_to_tasks_table.php
   sudo chown www-data:www-data /home/goisw/madnezz/madnezz-api/database/migrations/2025_01_14_000001_add_department_id_to_tasks_table.php
   ```

2. **Run the migration**:
   ```bash
   cd /home/goisw/madnezz/madnezz-api
   php artisan migrate
   ```

## Usage

### Creating a Task with Department
```php
$params = [
    'title' => 'Department Task',
    'description' => 'Task for Finance department',
    'priority' => 'HIGH',
    'created_by' => $userId,
    'organization_id' => $orgId,
    'department_id' => $financeDeptId, // New field
    // ... other fields
];

$task = $createTaskUseCase->execute($params);
```

### Querying Tasks by Department
```php
// Using Eloquent
$tasks = TaskModel::where('department_id', $departmentId)->get();

// With relationship
$department = DepartmentModel::find($departmentId);
$tasks = $department->tasks;
```

## Benefits

1. **Department-based Task Organization**: Tasks can be categorized by department
2. **Better Filtering**: Users can filter tasks by their department
3. **Department Workload Analysis**: Track tasks per department for analytics
4. **Access Control**: Future enhancement to restrict task visibility by department

## Considerations

- The `department_id` is nullable to maintain backward compatibility
- Foreign key constraint uses `onDelete('set null')` to prevent data loss
- Index added for performance when filtering by department
- Existing tasks will have `null` department_id after migration