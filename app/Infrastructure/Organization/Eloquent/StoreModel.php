<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class StoreModel extends Model
{
    use SoftDeletes;

    protected $table = 'stores';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'organization_id',
        'manager_id',
        'name',
        'code',
        'address',
        'city',
        'state',
        'zip_code',
        'phone',
        'active',
    ];

    protected $casts = [
        'id' => 'string',
        'organization_id' => 'string',
        'manager_id' => 'string',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(UserModel::class, 'store_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByOrganization($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeWithManager($query)
    {
        return $query->whereNotNull('manager_id');
    }

    public function scopeWithoutManager($query)
    {
        return $query->whereNull('manager_id');
    }
}