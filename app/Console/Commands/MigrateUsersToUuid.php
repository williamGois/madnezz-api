<?php

namespace App\Console\Commands;

use App\Models\User as OldUser;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel as NewUser;
use Illuminate\Console\Command;
use Ramsey\Uuid\Uuid;

class MigrateUsersToUuid extends Command
{
    protected $signature = 'users:migrate-to-uuid';
    protected $description = 'Migrate existing users from users table to users_ddd table with UUID';

    public function handle()
    {
        $this->info('Starting user migration to DDD architecture...');

        $oldUsers = OldUser::all();
        $migratedCount = 0;

        foreach ($oldUsers as $oldUser) {
            // Check if user already exists in new table
            $existingUser = NewUser::where('email', $oldUser->email)->first();
            
            if ($existingUser) {
                $this->warn("User {$oldUser->email} already exists in DDD table, skipping...");
                continue;
            }

            // Create new user with UUID
            $newUser = new NewUser();
            $newUser->id = Uuid::uuid4()->toString();
            $newUser->name = $oldUser->name;
            $newUser->email = $oldUser->email;
            $newUser->password = $oldUser->password; // Keep existing hash
            $newUser->email_verified_at = $oldUser->email_verified_at;
            $newUser->status = $oldUser->status ?? 'active';
            $newUser->last_login_at = $oldUser->last_login_at;
            $newUser->created_at = $oldUser->created_at;
            $newUser->updated_at = $oldUser->updated_at;
            
            $newUser->save();
            
            $this->info("Migrated user: {$oldUser->email} -> {$newUser->id}");
            $migratedCount++;
        }

        $this->info("Migration completed! Migrated {$migratedCount} users.");
        
        return 0;
    }
}