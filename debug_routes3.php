<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

// Before the kernel handles the request, let's trace route registration
$originalGet = null;

// Override the router's get method temporarily to trace registrations
app()->extend('router', function ($router) use (&$originalGet) {
    $originalGet = [$router, 'get'];
    
    // Create a wrapper to trace route registrations
    $routerWrapper = new class($router) {
        private $router;
        private $originalGet;
        
        public function __construct($router) {
            $this->router = $router;
        }
        
        public function get($uri, $action = null) {
            if (is_array($action) && isset($action['as']) && str_contains($action['as'], 'l5-swagger')) {
                echo "Registering route: " . $action['as'] . " => " . $uri . "\n";
            }
            return call_user_func([$this->router, 'get'], $uri, $action);
        }
        
        public function __call($method, $args) {
            return call_user_func_array([$this->router, $method], $args);
        }
    };
    
    return $routerWrapper;
});

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Now check what routes actually exist
echo "\n=== Final Registered Routes ===\n";
$routes = app('router')->getRoutes();
foreach ($routes as $route) {
    $name = $route->getName();
    if ($name && str_contains($name, 'l5-swagger')) {
        echo $name . " => " . $route->uri() . "\n";
    }
}