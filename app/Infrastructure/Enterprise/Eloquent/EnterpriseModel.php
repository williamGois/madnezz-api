<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Infrastructure\Organization\Eloquent\OrganizationModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;

class EnterpriseModel extends Model
{
    protected $table = 'enterprises';
    
    protected $keyType = 'string';
    
    public $incrementing = false;
    
    protected $fillable = [
        'id',
        'name',
        'code',
        'organization_id',
        'status',
        'description',
        'observations',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }
    
    public function stores(): HasMany
    {
        return $this->hasMany(OrganizationUnitModel::class, 'enterprise_id')
            ->where('type', 'STORE');
    }
    
    public function units(): HasMany
    {
        return $this->hasMany(OrganizationUnitModel::class, 'enterprise_id');
    }
    
    public function getStoreCountAttribute(): int
    {
        return $this->stores()->count();
    }
    
    public function getActiveStoreCountAttribute(): int
    {
        return $this->stores()->where('status', 'ACTIVE')->count();
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }
    
    public function scopeByOrganization($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
    
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
}