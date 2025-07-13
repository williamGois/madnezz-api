<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\Registered;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JwtAuthService
{
    public function register(array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Check if user already exists
            if (User::where('email', $data['email'])->exists()) {
                throw new BusinessRuleException('User with this email already exists');
            }
            
            // Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now() // Auto-verify for API
            ]);
            
            // Create JWT token
            $token = JWTAuth::fromUser($user);
            
            // Fire registered event
            event(new Registered($user));
            
            DB::commit();
            
            return [
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ];
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function login(array $credentials): array
    {
        // Attempt to authenticate and get JWT token
        $token = JWTAuth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password']
        ]);
        
        if (!$token) {
            throw new BusinessRuleException('Invalid credentials');
        }
        
        $user = auth()->user();
        
        if ($user->status === 'inactive') {
            JWTAuth::invalidate($token);
            throw new BusinessRuleException('Account is inactive');
        }
        
        // Update last login
        $user->update(['last_login_at' => now()]);
        
        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ];
    }

    public function logout(): void
    {
        try {
            // Invalidate the current token
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // Token already invalidated or expired
        }
    }

    public function refresh(): array
    {
        try {
            $newToken = JWTAuth::refresh();
            $user = JWTAuth::setToken($newToken)->toUser();
            
            return [
                'user' => $user,
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ];
        } catch (\Exception $e) {
            throw new BusinessRuleException('Unable to refresh token');
        }
    }

    public function me(): User
    {
        return auth()->user();
    }

    public function updateProfile(User $user, array $data): User
    {
        DB::beginTransaction();
        
        try {
            // Check if email is being changed and is unique
            if (isset($data['email']) && $data['email'] !== $user->email) {
                if (User::where('email', $data['email'])->where('id', '!=', $user->id)->exists()) {
                    throw new BusinessRuleException('Email already in use');
                }
            }
            
            $user->update($data);
            
            DB::commit();
            
            return $user->fresh();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function changePassword(User $user, array $data): void
    {
        if (!Hash::check($data['current_password'], $user->password)) {
            throw new BusinessRuleException('Current password is incorrect');
        }
        
        $user->update([
            'password' => Hash::make($data['new_password'])
        ]);
    }

    public function forgotPassword(string $email): void
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            throw new BusinessRuleException('User not found');
        }
        
        $status = Password::sendResetLink(['email' => $email]);
        
        if ($status !== Password::RESET_LINK_SENT) {
            throw new BusinessRuleException('Unable to send reset link');
        }
    }

    public function resetPassword(array $data): void
    {
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password)
            ])->save();
        });
        
        if ($status !== Password::PASSWORD_RESET) {
            throw new BusinessRuleException('Password reset failed');
        }
    }
}