<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    protected $table = 'tasks';
    
    protected $fillable = [
        'id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'organization_unit_id',
        'created_by',
        'tags',
    ];

    protected $casts = [
        'id' => 'string',
        'due_date' => 'datetime',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnitModel::class, 'organization_unit_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(UserModel::class, 'task_assignees', 'task_id', 'user_id');
    }
}