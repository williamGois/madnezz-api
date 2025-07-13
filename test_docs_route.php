<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Check if we can manually register the swagger docs route
echo "=== Attempting to manually register swagger docs route ===\n";

// Get the config
$configFactory = app(L5Swagger\ConfigFactory::class);
$config = $configFactory->documentationConfig('default');

if (isset($config['routes']['docs'])) {
    echo "Docs route path from config: " . $config['routes']['docs'] . "\n";
    
    // Try to register it manually
    app('router')->get($config['routes']['docs'], [
        'as' => 'l5-swagger.default.docs.manual',
        'uses' => '\L5Swagger\Http\Controllers\SwaggerController@docs',
    ]);
    
    echo "Manual registration attempted\n";
}

// Check all routes with 'docs' in the URI
echo "\n=== All routes with 'docs' in URI ===\n";
$routes = app('router')->getRoutes();
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'docs')) {
        echo "URI: " . $route->uri() . "\n";
        echo "Name: " . ($route->getName() ?? 'unnamed') . "\n";
        echo "Action: " . $route->getActionName() . "\n";
        echo "---\n";
    }
}

// Check if there's a route conflict
echo "\n=== Checking for route conflicts ===\n";
$docsRoutes = [];
foreach ($routes as $route) {
    if ($route->uri() === 'docs') {
        $docsRoutes[] = [
            'name' => $route->getName() ?? 'unnamed',
            'action' => $route->getActionName(),
            'methods' => $route->methods()
        ];
    }
}

if (count($docsRoutes) > 0) {
    echo "Found " . count($docsRoutes) . " route(s) with URI 'docs':\n";
    foreach ($docsRoutes as $r) {
        echo "- Name: " . $r['name'] . "\n";
        echo "  Action: " . $r['action'] . "\n";
        echo "  Methods: " . implode(', ', $r['methods']) . "\n";
    }
} else {
    echo "No routes found with URI 'docs'\n";
}

// Let's check if the SwaggerController exists and has the docs method
echo "\n=== Checking SwaggerController ===\n";
$controllerClass = '\L5Swagger\Http\Controllers\SwaggerController';
if (class_exists($controllerClass)) {
    echo "SwaggerController class exists\n";
    $methods = get_class_methods($controllerClass);
    echo "Methods: " . implode(', ', $methods) . "\n";
    
    if (in_array('docs', $methods)) {
        echo "docs() method exists\n";
    } else {
        echo "docs() method NOT found!\n";
    }
} else {
    echo "SwaggerController class NOT found!\n";
}