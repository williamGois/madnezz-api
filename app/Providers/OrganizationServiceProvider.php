<?php

namespace App\Providers;

use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\Organization\Repositories\PositionRepositoryInterface;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\Organization\Services\AccessControlService;
use App\Domain\Organization\Services\HierarchyPermissionService;
use App\Infrastructure\Organization\Repositories\EloquentDepartmentRepository;
use App\Infrastructure\Organization\Repositories\EloquentOrganizationRepository;
use App\Infrastructure\Organization\Repositories\EloquentOrganizationUnitRepository;
use App\Infrastructure\Organization\Repositories\EloquentPositionRepository;
use App\Infrastructure\Organization\Repositories\EloquentStoreRepository;
use App\Application\UseCases\CreateOrganization\CreateOrganizationUseCase;
use App\Application\Organization\UseCases\CreateRegion\CreateRegionUseCase;
use App\Application\UseCases\CreateStore\CreateStoreUseCase;
use Illuminate\Support\ServiceProvider;

class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrganizationRepositoryInterface::class, EloquentOrganizationRepository::class);
        $this->app->bind(OrganizationUnitRepositoryInterface::class, EloquentOrganizationUnitRepository::class);
        $this->app->bind(DepartmentRepositoryInterface::class, EloquentDepartmentRepository::class);
        $this->app->bind(PositionRepositoryInterface::class, EloquentPositionRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, EloquentStoreRepository::class);
        
        $this->app->singleton(HierarchyPermissionService::class);
        $this->app->singleton(AccessControlService::class);
        
        // Use Cases
        $this->app->singleton(CreateOrganizationUseCase::class);
        $this->app->singleton(CreateRegionUseCase::class);
        $this->app->singleton(CreateStoreUseCase::class);
    }

    public function boot(): void
    {
        //
    }
}