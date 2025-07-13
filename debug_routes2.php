<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Directly check what's happening in the route registration
$configFactory = app(\L5Swagger\ConfigFactory::class);
$documentations = config('l5-swagger.documentations', []);

echo "=== Route Registration Debug ===\n";
foreach (array_keys($documentations) as $name) {
    echo "\nDocumentation: $name\n";
    
    $config = $configFactory->documentationConfig($name);
    
    echo "Has 'routes' key: " . (isset($config['routes']) ? 'YES' : 'NO') . "\n";
    
    if (isset($config['routes'])) {
        echo "Routes array:\n";
        print_r($config['routes']);
        
        echo "\nChecking specific routes:\n";
        echo "  - Has 'api' route: " . (isset($config['routes']['api']) ? 'YES' : 'NO') . "\n";
        echo "  - Has 'docs' route: " . (isset($config['routes']['docs']) ? 'YES' : 'NO') . "\n";
        echo "  - Has 'oauth2_callback' route: " . (isset($config['routes']['oauth2_callback']) ? 'YES' : 'NO') . "\n";
    }
}

// Check if we can manually build the route
echo "\n=== Manual Route Building ===\n";
$routeName = 'l5-swagger.default.docs';
echo "Looking for route: $routeName\n";
if (app('router')->has($routeName)) {
    echo "Route exists!\n";
    echo "URL: " . route($routeName) . "\n";
} else {
    echo "Route does NOT exist!\n";
    
    // Try to understand why
    $config = $configFactory->documentationConfig('default');
    if (isset($config['routes']['docs'])) {
        echo "But 'docs' is in the merged config with value: " . $config['routes']['docs'] . "\n";
    }
}