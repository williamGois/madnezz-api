<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Contracts\AuthServiceInterface;
use App\Application\DTOs\Auth\LoginUserDTO;
use App\Application\DTOs\Auth\RegisterUserDTO;
use App\Application\DTOs\User\ChangePasswordDTO;
use App\Application\DTOs\User\UpdateUserProfileDTO;
use App\Domain\User\ValueObjects\UserId;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        private AuthServiceInterface $authService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User registered successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $dto = new RegisterUserDTO(
                $request->validated()['name'],
                $request->validated()['email'],
                $request->validated()['password']
            );

            $result = $this->authService->register($dto);

            return $this->createdResponse([
                'user' => $this->convertDomainUserToArray($result->user),
                'access_token' => $result->token,
                'token_type' => $result->tokenType,
                'expires_in' => $result->expiresIn
            ], 'User registered successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/login",
     *     summary="Login user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $dto = new LoginUserDTO(
                $request->validated()['email'],
                $request->validated()['password']
            );

            $result = $this->authService->login($dto);

            return $this->successResponse([
                'user' => $this->convertDomainUserToArray($result->user),
                'access_token' => $result->token,
                'token_type' => $result->tokenType,
                'expires_in' => $result->expiresIn
            ], 'Login successful');
        } catch (\Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/logout",
     *     summary="Logout user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout successful")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout();
            
            return $this->successResponse(null, 'Logout successful');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/refresh",
     *     summary="Refresh JWT token",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     )
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->refresh();
            
            return $this->successResponse([
                'user' => $this->convertDomainUserToArray($result->user),
                'access_token' => $result->token,
                'token_type' => $result->tokenType,
                'expires_in' => $result->expiresIn
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/v1/auth/me",
     *     summary="Get current user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $this->authService->getCurrentUser();
            
            return $this->successResponse(
                $this->convertDomainUserToArray($user),
                'User retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    /**
     * @OA\Put(
     *     path="/v1/auth/profile",
     *     summary="Update user profile",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Jane Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="jane@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully"
     *     )
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $currentUser = $this->authService->getCurrentUser();
            
            $dto = new UpdateUserProfileDTO(
                $request->validated()['name'],
                $request->validated()['email']
            );

            $user = $this->authService->updateProfile($currentUser->getId(), $dto);
            
            return $this->successResponse(
                $this->convertDomainUserToArray($user),
                'Profile updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/v1/auth/change-password",
     *     summary="Change user password",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","new_password","new_password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password"),
     *             @OA\Property(property="new_password", type="string", format="password"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully"
     *     )
     * )
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $currentUser = $this->authService->getCurrentUser();
            
            $dto = new ChangePasswordDTO(
                $request->validated()['current_password'],
                $request->validated()['new_password']
            );

            $this->authService->changePassword($currentUser->getId(), $dto);
            
            return $this->successResponse(null, 'Password changed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    private function convertDomainUserToArray(\App\Domain\User\Entities\User $user): array
    {
        return [
            'id' => $user->getId()->getValue(),
            'name' => $user->getName()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'status' => $user->getStatus()->getValue(),
            'email_verified_at' => $user->getEmailVerifiedAt()?->format('Y-m-d\TH:i:s.u\Z'),
            'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d\TH:i:s.u\Z'),
            'created_at' => $user->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}