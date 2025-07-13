<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Madnezz API",
 *     description="API for Madnezz application",
 *     @OA\Contact(
 *         name="API Support",
 *         email="support@madnezz.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:9000/api",
 *     description="Local Development Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Authentication endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Users",
 *     description="User management endpoints"
 * )
 */
class SwaggerController extends Controller
{
    // This controller is just for Swagger documentation
}