<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'status' => $this->status ?? 'active',
            'roles' => $this->when(
                $this->relationLoaded('roles'),
                fn() => $this->roles->pluck('name')
            ),
            'permissions' => $this->when(
                $request->user()?->can('view-permissions'),
                fn() => $this->getAllPermissions()->pluck('name')
            ),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}