<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight requests
        if ($request->getMethod() === "OPTIONS") {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }
        
        // CORS headers
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://localhost:3002',
            'http://localhost:3003',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001',
            'http://127.0.0.1:3002',
            'http://127.0.0.1:3003'
        ];
        
        $origin = $request->headers->get('Origin');
        if (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // CSP for API - allow inline styles and external fonts for Laravel's default page and Swagger UI
        $isSwaggerRoute = $request->is('api/documentation*') || $request->is('docs/*');
        
        if ($isSwaggerRoute) {
            // More permissive CSP for Swagger UI
            $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://cdn.jsdelivr.net; font-src 'self' https://fonts.bunny.net data:; img-src 'self' data: https://validator.swagger.io; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; worker-src 'self' blob:; child-src 'self' blob:");
        } else {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; script-src 'self' 'unsafe-inline'");
        }
        
        // API specific headers
        $response->headers->set('X-API-Version', config('app.api_version', 'v1'));
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        // Remove server information
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');
        
        return $response;
    }
}