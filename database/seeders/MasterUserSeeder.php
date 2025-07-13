<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class MasterUserSeeder extends Seeder
{
    public function run(): void
    {
        // Criar usuário MASTER
        UserModel::updateOrCreate(
            ['email' => 'master@madnezz.com'],
            [
                'id' => Uuid::uuid4()->toString(),
                'name' => 'Master Admin',
                'email' => 'master@madnezz.com',
                'password' => Hash::make('Master@123'),
                'hierarchy_role' => 'MASTER',
                'status' => 'active',
                'organization_id' => null, // MASTER não pertence a organização específica
                'store_id' => null, // MASTER não pertence a loja específica
                'phone' => '+55 11 99999-9999',
                'permissions' => json_encode(['*']), // Todas as permissões
                'context_data' => null,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✅ Usuário MASTER criado com sucesso!');
        $this->command->line('');
        $this->command->line('=== CREDENCIAIS DO USUÁRIO MASTER ===');
        $this->command->line('Email: master@madnezz.com');
        $this->command->line('Senha: Master@123');
        $this->command->line('Papel: MASTER (acesso total)');
        $this->command->line('=====================================');
    }
}