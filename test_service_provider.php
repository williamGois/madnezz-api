<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

// Get list of service providers
$loadedProviders = $app->getLoadedProviders();

echo "=== Checking Service Provider Loading ===\n";
echo "L5SwaggerServiceProvider loaded: " . 
     (isset($loadedProviders[\L5Swagger\L5SwaggerServiceProvider::class]) ? 'YES' : 'NO') . "\n";

// Boot the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check again after bootstrap
$loadedProviders = $app->getLoadedProviders();
echo "\nAfter bootstrap:\n";
echo "L5SwaggerServiceProvider loaded: " . 
     (isset($loadedProviders[\L5Swagger\L5SwaggerServiceProvider::class]) ? 'YES' : 'NO') . "\n";

// Check if routes file was loaded
echo "\n=== Checking if routes.php was executed ===\n";

// Let's trace the route loading by checking the file
$routesFile = __DIR__ . '/vendor/darkaonline/l5-swagger/src/routes.php';
echo "Routes file exists: " . (file_exists($routesFile) ? 'YES' : 'NO') . "\n";

// Let's check the actual routes registered
echo "\n=== L5-Swagger Routes Found ===\n";
$routes = Route::getRoutes();
$l5SwaggerRoutes = [];
foreach ($routes as $route) {
    $name = $route->getName();
    if ($name && str_contains($name, 'l5-swagger')) {
        $l5SwaggerRoutes[$name] = $route->uri();
    }
}

foreach ($l5SwaggerRoutes as $name => $uri) {
    echo "$name => $uri\n";
}

// Let's check what's in the config at runtime
echo "\n=== Runtime Config Check ===\n";
$documentations = config('l5-swagger.documentations', []);
echo "Documentations found: " . json_encode(array_keys($documentations)) . "\n";

foreach ($documentations as $docName => $docConfig) {
    echo "\nDocumentation '$docName':\n";
    echo "  Has 'routes' key: " . (isset($docConfig['routes']) ? 'YES' : 'NO') . "\n";
    if (isset($docConfig['routes'])) {
        echo "  Routes: " . json_encode(array_keys($docConfig['routes'])) . "\n";
    }
}