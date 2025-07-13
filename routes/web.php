<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => config('app.api_version', 'v1'),
        'status' => 'running',
        'endpoints' => [
            'health' => '/api/health',
            'auth' => [
                'login' => '/api/v1/auth/login',
                'register' => '/api/v1/auth/register',
                'profile' => '/api/v1/auth/profile'
            ]
        ]
    ]);
});

// Additional route to serve api-docs.json directly
Route::get('/api-docs.json', function () {
    $path = storage_path('api-docs/api-docs.json');
    if (file_exists($path)) {
        return response()->file($path, ['Content-Type' => 'application/json']);
    }
    return response()->json(['error' => 'API documentation not found', 'path' => $path], 404);
});

// Serve Swagger UI assets
Route::get('/swagger-docs/asset/{asset}', function ($asset) {
    $path = base_path('vendor/swagger-api/swagger-ui/dist/' . $asset);
    
    if (!file_exists($path)) {
        abort(404);
    }
    
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];
    
    $extension = pathinfo($asset, PATHINFO_EXTENSION);
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    
    return response()->file($path, ['Content-Type' => $mimeType]);
})->where('asset', '.*')->name('l5-swagger.default.asset');

// Simple Swagger documentation route
Route::get('/api/documentation', function () {
    return view('swagger');
});

// Route to serve the API docs JSON
Route::get('/swagger-docs/{jsonFile}', function ($jsonFile) {
    $path = storage_path('api-docs/' . $jsonFile);
    if (file_exists($path)) {
        return response()->file($path, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*'
        ]);
    }
    return response()->json(['error' => 'Documentation file not found'], 404);
})->where('jsonFile', '.*')->name('l5-swagger.default.docs');


