<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Eloquent;

use App\Infrastructure\User\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PositionModel extends Model
{
    protected $table = 'positions';
    
    protected $fillable = [
        'id',
        'organization_id',
        'organization_unit_id',
        'user_id',
        'level',
        'title',
        'active',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'organization_unit_id' => 'string',
        'user_id' => 'string',
        'active' => 'boolean',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnitModel::class, 'organization_unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            DepartmentModel::class,
            'position_departments',
            'position_id',
            'department_id'
        );
    }
}