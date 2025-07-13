<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Contracts\TokenServiceInterface;
use App\Domain\User\Entities\User;
use App\Infrastructure\Persistence\Mappers\UserMapper;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JwtTokenService implements TokenServiceInterface
{
    public function generateToken(User $user): string
    {
        // Get the actual UserModel from database with all fields including hierarchy_role
        $model = UserModel::find($user->getId()->getValue());
        
        if (!$model) {
            // If not found, create from domain entity
            $model = UserMapper::toEloquent($user);
        }
        
        return JWTAuth::fromUser($model);
    }

    public function refreshToken(): string
    {
        return JWTAuth::refresh();
    }

    public function invalidateToken(): void
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // Token already invalidated or expired
        }
    }

    public function getTokenTtl(): int
    {
        return config('jwt.ttl') * 60; // Convert minutes to seconds
    }

    public function getCurrentUser(): ?User
    {
        try {
            $model = JWTAuth::parseToken()->authenticate();
            
            if (!$model instanceof UserModel) {
                return null;
            }
            
            return UserMapper::toDomain($model);
        } catch (\Exception $e) {
            return null;
        }
    }
}