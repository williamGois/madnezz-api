<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Infrastructure\User\Repositories\EloquentHierarchicalUserRepository;
use App\Domain\Enterprise\Repositories\EnterpriseRepositoryInterface;
use App\Infrastructure\Enterprise\Repositories\EloquentEnterpriseRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // User Management bindings
        $this->app->bind(HierarchicalUserRepositoryInterface::class, EloquentHierarchicalUserRepository::class);
        
        // Enterprise Management bindings
        $this->app->bind(EnterpriseRepositoryInterface::class, EloquentEnterpriseRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
