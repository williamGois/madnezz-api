<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

// Boot the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check the actual route registration
echo "=== Checking L5-Swagger Route Registration ===\n\n";

// Get the config factory
$configFactory = app(\L5Swagger\ConfigFactory::class);

// Get the merged config for 'default'
$config = $configFactory->documentationConfig('default');

echo "Merged config routes:\n";
print_r($config['routes']);

// Now let's manually try to register the missing route
echo "\n=== Attempting to register missing route ===\n";

Route::group(['namespace' => 'L5Swagger'], function () use ($config) {
    $name = 'default';
    
    if (isset($config['routes']['docs'])) {
        echo "Registering docs route: " . $config['routes']['docs'] . "\n";
        
        Route::get($config['routes']['docs'], [
            'as' => 'l5-swagger.'.$name.'.docs',
            'middleware' => $config['routes']['middleware']['docs'] ?? [],
            'uses' => '\L5Swagger\Http\Controllers\SwaggerController@docs',
        ]);
    }
});

// Check if it worked
if (Route::has('l5-swagger.default.docs')) {
    echo "\nSuccess! Route 'l5-swagger.default.docs' is now registered!\n";
    echo "URL would be: " . url($config['routes']['docs']) . "\n";
} else {
    echo "\nFailed to register the route.\n";
}

// Let's also check what version of l5-swagger is installed
$composerLock = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true);
foreach ($composerLock['packages'] as $package) {
    if ($package['name'] === 'darkaonline/l5-swagger') {
        echo "\nL5-Swagger version: " . $package['version'] . "\n";
        break;
    }
}