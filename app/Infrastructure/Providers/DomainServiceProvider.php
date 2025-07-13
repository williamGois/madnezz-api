<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Contracts\AuthServiceInterface;
use App\Application\Contracts\TokenServiceInterface;
use App\Application\Services\AuthService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Infrastructure\Organization\Repositories\EloquentOrganizationRepository;
use App\Infrastructure\Organization\Repositories\EloquentStoreRepository;
use App\Infrastructure\Task\Repositories\EloquentTaskRepository;
use App\Infrastructure\Services\JwtTokenService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind repository interfaces to implementations
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        $this->app->bind(
            OrganizationRepositoryInterface::class,
            EloquentOrganizationRepository::class
        );

        $this->app->bind(
            StoreRepositoryInterface::class,
            EloquentStoreRepository::class
        );

        $this->app->bind(
            TaskRepositoryInterface::class,
            EloquentTaskRepository::class
        );

        // Bind service interfaces to implementations
        $this->app->bind(
            TokenServiceInterface::class,
            JwtTokenService::class
        );

        $this->app->bind(
            AuthServiceInterface::class,
            AuthService::class
        );
    }

    public function boot(): void
    {
        //
    }
}