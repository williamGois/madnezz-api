<?php

namespace App\Providers;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentHierarchicalUserRepository;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(HierarchicalUserRepositoryInterface::class, EloquentHierarchicalUserRepository::class);
    }

    public function boot(): void
    {
        //
    }
}