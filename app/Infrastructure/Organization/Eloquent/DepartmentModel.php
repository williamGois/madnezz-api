<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DepartmentModel extends Model
{
    protected $table = 'departments';
    
    protected $fillable = [
        'id',
        'organization_id',
        'type',
        'name',
        'description',
        'active',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'active' => 'boolean',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(
            PositionModel::class,
            'position_departments',
            'department_id',
            'position_id'
        );
    }
}