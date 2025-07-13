<?php

declare(strict_types=1);

namespace App\Infrastructure\Task\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;

class TaskModel extends Model
{
    protected $table = 'tasks';
    
    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'created_by',
        'organization_id',
        'organization_unit_id',
        'parent_task_id',
        'due_date',
        'completed_at'
    ];
    
    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }
    
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }
    
    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnitModel::class, 'organization_unit_id');
    }
    
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(TaskModel::class, 'parent_task_id');
    }
    
    public function subtasks(): HasMany
    {
        return $this->hasMany(TaskModel::class, 'parent_task_id');
    }
    
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(UserModel::class, 'task_assignees', 'task_id', 'user_id')
                    ->withPivot('assigned_at')
                    ->withTimestamps();
    }
    
    public function scopeByOrganization($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
    
    public function scopeByOrganizationUnit($query, string $organizationUnitId)
    {
        return $query->where('organization_unit_id', $organizationUnitId);
    }
    
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')
                     ->where('due_date', '<', now())
                     ->whereNotIn('status', ['DONE', 'CANCELLED']);
    }
    
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->whereHas('assignees', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }
}