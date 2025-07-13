<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationModel extends Model
{
    protected $table = 'organizations';
    
    protected $fillable = [
        'id',
        'name',
        'code',
        'active',
    ];

    protected $casts = [
        'id' => 'string',
        'active' => 'boolean',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function organizationUnits(): HasMany
    {
        return $this->hasMany(OrganizationUnitModel::class, 'organization_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(DepartmentModel::class, 'organization_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(PositionModel::class, 'organization_id');
    }
}