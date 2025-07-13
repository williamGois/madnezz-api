<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Get all registered routes
$routes = app('router')->getRoutes();

echo "=== L5-Swagger Routes ===\n";
foreach ($routes as $route) {
    $name = $route->getName();
    if ($name && str_contains($name, 'l5-swagger')) {
        echo "Route Name: " . $name . "\n";
        echo "URI: " . $route->uri() . "\n";
        echo "Methods: " . implode(', ', $route->methods()) . "\n";
        echo "---\n";
    }
}

// Check configuration
echo "\n=== L5-Swagger Configuration ===\n";
$config = config('l5-swagger');
echo "Default: " . ($config['default'] ?? 'not set') . "\n";
echo "Documentations: " . json_encode(array_keys($config['documentations'] ?? [])) . "\n";

// Check merged configuration for 'default' documentation
$configFactory = app(\L5Swagger\ConfigFactory::class);
try {
    $mergedConfig = $configFactory->documentationConfig('default');
    echo "\n=== Merged Configuration for 'default' ===\n";
    echo "Routes in merged config:\n";
    if (isset($mergedConfig['routes'])) {
        foreach ($mergedConfig['routes'] as $routeKey => $routePath) {
            if (is_string($routePath)) {
                echo "  - $routeKey: $routePath\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "Error getting merged config: " . $e->getMessage() . "\n";
}