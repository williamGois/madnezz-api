<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class AuthService
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
            
            // Skip role assignment for now - roles table not ready
            // $user->assignRole('user');
            
            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;
            
            // Fire registered event
            event(new Registered($user));
            
            DB::commit();
            
            return [
                'user' => $user,
                'token' => $token
            ];
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();
        
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw new BusinessRuleException('Invalid credentials');
        }
        
        if ($user->status === 'inactive') {
            throw new BusinessRuleException('Account is inactive');
        }
        
        // Revoke existing tokens if specified
        if (isset($credentials['revoke_existing_tokens']) && $credentials['revoke_existing_tokens']) {
            $user->tokens()->delete();
        }
        
        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Update last login
        $user->update(['last_login_at' => now()]);
        
        return [
            'user' => $user,
            'token' => $token
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
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
        
        // Revoke all tokens except current
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
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
            
            // Revoke all tokens
            $user->tokens()->delete();
        });
        
        if ($status !== Password::PASSWORD_RESET) {
            throw new BusinessRuleException('Password reset failed');
        }
    }
}