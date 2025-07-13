<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class CreateMasterUser extends Command
{
    protected $signature = 'madnezz:create-master {--force : Force creation even if user exists}';
    protected $description = 'Create the initial MASTER user for the Madnezz system';

    public function handle()
    {
        $email = 'master@madnezz.com';
        $password = 'Master@123';

        // Check if user already exists
        $existingUser = UserModel::where('email', $email)->first();
        
        if ($existingUser && !$this->option('force')) {
            $this->error('❌ Usuário MASTER já existe!');
            $this->line('Use --force para recriar o usuário.');
            return 1;
        }

        if ($existingUser && $this->option('force')) {
            $existingUser->delete();
            $this->info('🔄 Usuário MASTER existente removido.');
        }

        try {
            // Create MASTER user
            $user = UserModel::create([
                'id' => Uuid::uuid4()->toString(),
                'name' => 'Master Admin',
                'email' => $email,
                'password' => Hash::make($password),
                'hierarchy_role' => 'MASTER',
                'status' => 'active',
                'organization_id' => null,
                'store_id' => null,
                'phone' => '+55 11 99999-9999',
                'permissions' => json_encode(['*']),
                'context_data' => null,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info('✅ Usuário MASTER criado com sucesso!');
            $this->line('');
            $this->line('════════════════════════════════════════');
            $this->line('🔑 CREDENCIAIS DO USUÁRIO MASTER');
            $this->line('════════════════════════════════════════');
            $this->line('📧 Email: ' . $email);
            $this->line('🔒 Senha: ' . $password);
            $this->line('👑 Papel: MASTER (acesso total ao sistema)');
            $this->line('🆔 ID: ' . $user->id);
            $this->line('📱 Telefone: +55 11 99999-9999');
            $this->line('════════════════════════════════════════');
            $this->line('');
            $this->info('💡 Você pode usar essas credenciais para fazer login no sistema.');
            $this->info('💡 O usuário MASTER pode criar organizações e assumir qualquer papel.');
            
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Erro ao criar usuário MASTER: ' . $e->getMessage());
            return 1;
        }
    }
}