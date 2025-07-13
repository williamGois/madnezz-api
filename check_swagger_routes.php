<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Get all routes
$routes = app('router')->getRoutes();

echo "=== All L5-Swagger Routes ===\n";
foreach ($routes as $route) {
    $name = $route->getName();
    if ($name && str_contains($name, 'l5-swagger')) {
        echo "Route Name: " . $name . "\n";
        echo "URI: " . $route->uri() . "\n";
        echo "Methods: " . implode(', ', $route->methods()) . "\n";
        echo "Action: " . $route->getActionName() . "\n";
        echo "---\n";
    }
}

// Check config
echo "\n=== L5-Swagger Config ===\n";
echo "Documentations:\n";
$documentations = config('l5-swagger.documentations', []);
foreach ($documentations as $name => $config) {
    echo "- Documentation '$name':\n";
    if (isset($config['routes'])) {
        echo "  Routes configured: " . json_encode($config['routes']) . "\n";
    } else {
        echo "  No routes configured\n";
    }
}

// Check if service provider is loaded
echo "\n=== Service Provider Check ===\n";
$providers = app()->getLoadedProviders();
$swaggerProviderLoaded = isset($providers['L5Swagger\L5SwaggerServiceProvider']);
echo "L5SwaggerServiceProvider loaded: " . ($swaggerProviderLoaded ? 'YES' : 'NO') . "\n";

// Check route file existence
echo "\n=== Route File Check ===\n";
$routeFile = __DIR__ . '/vendor/darkaonline/l5-swagger/src/routes.php';
echo "Route file exists: " . (file_exists($routeFile) ? 'YES' : 'NO') . "\n";