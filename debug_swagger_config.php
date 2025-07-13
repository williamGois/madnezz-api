<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Get ConfigFactory
$configFactory = app(L5Swagger\ConfigFactory::class);

echo "=== Default Documentation Config ===\n";
$config = $configFactory->documentationConfig('default');
echo "Full config:\n";
print_r($config);

echo "\n=== Routes Section ===\n";
if (isset($config['routes'])) {
    echo "Routes config exists\n";
    print_r($config['routes']);
    
    if (isset($config['routes']['docs'])) {
        echo "\nDocs route is set: " . $config['routes']['docs'] . "\n";
    } else {
        echo "\nDocs route is NOT set!\n";
    }
} else {
    echo "Routes config does NOT exist\n";
}

// Let's also check the raw config
echo "\n=== Raw Config Check ===\n";
echo "l5-swagger.documentations.default.routes:\n";
$rawRoutes = config('l5-swagger.documentations.default.routes');
print_r($rawRoutes);

echo "\nl5-swagger.defaults.routes:\n";
$defaultRoutes = config('l5-swagger.defaults.routes');
print_r($defaultRoutes);

// Check if the docs route specifically exists in the merged config
echo "\n=== Checking specific route keys ===\n";
$documentations = config('l5-swagger.documentations', []);
foreach (array_keys($documentations) as $name) {
    $docConfig = $configFactory->documentationConfig($name);
    echo "Documentation '$name':\n";
    echo "  Has 'routes' key: " . (isset($docConfig['routes']) ? 'YES' : 'NO') . "\n";
    if (isset($docConfig['routes'])) {
        echo "  Has 'api' route: " . (isset($docConfig['routes']['api']) ? 'YES' : 'NO') . "\n";
        echo "  Has 'docs' route: " . (isset($docConfig['routes']['docs']) ? 'YES' : 'NO') . "\n";
        if (isset($docConfig['routes']['docs'])) {
            echo "  Docs route value: " . $docConfig['routes']['docs'] . "\n";
        }
    }
}