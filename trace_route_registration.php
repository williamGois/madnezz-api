<?php

// Let's trace what happens during route registration

require __DIR__.'/vendor/autoload.php';

// Before bootstrapping, let's replace the routes.php temporarily
$originalRoutesContent = file_get_contents(__DIR__ . '/vendor/darkaonline/l5-swagger/src/routes.php');

// Create a modified version with debug output
$debugRoutesContent = <<<'PHP'
<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use L5Swagger\ConfigFactory;
use L5Swagger\Http\Middleware\Config as L5SwaggerConfig;

Route::group(['namespace' => 'L5Swagger'], static function (Router $router) {
    $configFactory = resolve(ConfigFactory::class);

    /** @var array<string,string> $documentations */
    $documentations = config('l5-swagger.documentations', []);
    
    echo "=== L5-Swagger Route Registration Debug ===\n";
    echo "Number of documentations: " . count($documentations) . "\n";

    foreach (array_keys($documentations) as $name) {
        echo "\nProcessing documentation: $name\n";
        
        $config = $configFactory->documentationConfig($name);
        
        echo "Config has 'routes' key: " . (isset($config['routes']) ? 'YES' : 'NO') . "\n";

        if (! isset($config['routes'])) {
            echo "Skipping - no routes config\n";
            continue;
        }

        $groupOptions = $config['routes']['group_options'] ?? [];

        if (! isset($groupOptions['middleware'])) {
            $groupOptions['middleware'] = [];
        }

        if (is_string($groupOptions['middleware'])) {
            $groupOptions['middleware'] = [$groupOptions['middleware']];
        }

        $groupOptions['l5-swagger.documentation'] = $name;
        $groupOptions['middleware'][] = L5SwaggerConfig::class;

        Route::group($groupOptions, static function (Router $router) use ($name, $config) {
            echo "Inside route group for $name\n";
            
            if (isset($config['routes']['api'])) {
                echo "Registering API route: " . $config['routes']['api'] . "\n";
                $router->get($config['routes']['api'], [
                    'as' => 'l5-swagger.'.$name.'.api',
                    'middleware' => $config['routes']['middleware']['api'] ?? [],
                    'uses' => '\L5Swagger\Http\Controllers\SwaggerController@api',
                ]);
            }

            if (isset($config['routes']['docs'])) {
                echo "Registering DOCS route: " . $config['routes']['docs'] . "\n";
                $router->get($config['routes']['docs'], [
                    'as' => 'l5-swagger.'.$name.'.docs',
                    'middleware' => $config['routes']['middleware']['docs'] ?? [],
                    'uses' => '\L5Swagger\Http\Controllers\SwaggerController@docs',
                ]);

                echo "Registering ASSET route: " . $config['routes']['docs'] . "/asset/{asset}\n";
                $router->get($config['routes']['docs'].'/asset/{asset}', [
                    'as' => 'l5-swagger.'.$name.'.asset',
                    'middleware' => $config['routes']['middleware']['asset'] ?? [],
                    'uses' => '\L5Swagger\Http\Controllers\SwaggerAssetController@index',
                ]);
            } else {
                echo "NOT registering DOCS route - not set in config\n";
            }

            if (isset($config['routes']['oauth2_callback'])) {
                echo "Registering OAuth2 callback route: " . $config['routes']['oauth2_callback'] . "\n";
                $router->get($config['routes']['oauth2_callback'], [
                    'as' => 'l5-swagger.'.$name.'.oauth2_callback',
                    'middleware' => $config['routes']['middleware']['oauth2_callback'] ?? [],
                    'uses' => '\L5Swagger\Http\Controllers\SwaggerController@oauth2Callback',
                ]);
            }
        });
    }
    
    echo "\n=== Route Registration Complete ===\n";
});
PHP;

// Write the debug version
file_put_contents(__DIR__ . '/vendor/darkaonline/l5-swagger/src/routes.php', $debugRoutesContent);

// Now bootstrap the application
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Check the registered routes
echo "\n=== Registered Routes ===\n";
$routes = app('router')->getRoutes();
foreach ($routes as $route) {
    $name = $route->getName();
    if ($name && str_contains($name, 'l5-swagger')) {
        echo "Route: " . $name . " => " . $route->uri() . "\n";
    }
}

// Restore the original file
file_put_contents(__DIR__ . '/vendor/darkaonline/l5-swagger/src/routes.php', $originalRoutesContent);

echo "\n=== Original routes.php restored ===\n";