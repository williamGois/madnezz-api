<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Infrastructure\Enterprise\Eloquent\EnterpriseModel;

class OrganizationUnitModel extends Model
{
    protected $table = 'organization_units';
    
    protected $fillable = [
        'id',
        'organization_id',
        'enterprise_id',
        'name',
        'code',
        'type',
        'parent_id',
        'active',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'enterprise_id' => 'string',
        'parent_id' => 'string',
        'active' => 'boolean',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnitModel::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrganizationUnitModel::class, 'parent_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(PositionModel::class, 'organization_unit_id');
    }
    
    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(EnterpriseModel::class, 'enterprise_id');
    }
}