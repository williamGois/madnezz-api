<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\BusinessRuleException;
use App\Infrastructure\Providers\DomainServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        DomainServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Custom middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
            'org.context' => \App\Http\Middleware\OrganizationContextMiddleware::class,
            'hierarchy.access' => \App\Http\Middleware\HierarchyAccessMiddleware::class,
            'hierarchy.filter' => \App\Http\Middleware\HierarchyFilterMiddleware::class,
            'cache.kanban' => \App\Http\Middleware\CacheKanbanMiddleware::class,
            'visible.stores' => \App\Http\Middleware\VisibleStoresMiddleware::class,
        ]);
        
        
        // Apply security headers globally
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (BusinessRuleException $e) {
            return $e->render();
        });
    })->create();
