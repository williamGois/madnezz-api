<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;
use App\Infrastructure\Organization\Eloquent\OrganizationModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;

class UserModel extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles;

    protected $table = 'users_ddd';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'status',
        'hierarchy_role',
        'organization_id',
        'organization_unit_id',
        'phone',
        'permissions',
        'context_data',
        'email_verified_at',
        'last_login_at',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'permissions' => 'array',
        'context_data' => 'array',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'hierarchy_role' => $this->hierarchy_role,
        ];
    }
    
    public function isMaster(): bool
    {
        return $this->hierarchy_role === 'MASTER';
    }
    
    // Relationships
    public function organization()
    {
        return $this->belongsTo(\App\Infrastructure\Organization\Eloquent\OrganizationModel::class, 'organization_id');
    }
    
    public function organizationUnit()
    {
        return $this->belongsTo(\App\Infrastructure\Organization\Eloquent\OrganizationUnitModel::class, 'organization_unit_id');
    }
    
    public function managedStores()
    {
        return $this->hasMany(\App\Infrastructure\Organization\Eloquent\OrganizationUnitModel::class, 'manager_id');
    }
    
    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }
    
    public function assignedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_assignees', 'user_id', 'task_id');
    }
}