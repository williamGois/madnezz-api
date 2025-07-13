<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // Create main organization
        $organizationId = Uuid::uuid4()->toString();
        
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => 'Madnezz Corporation',
            'code' => 'MADNEZZ',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create company unit (GO level)
        $companyUnitId = Uuid::uuid4()->toString();
        DB::table('organization_units')->insert([
            'id' => $companyUnitId,
            'organization_id' => $organizationId,
            'name' => 'Madnezz Corporation',
            'code' => 'COMPANY',
            'type' => 'company',
            'parent_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create regional units (GR level)
        $regionalUnits = [
            ['name' => 'Região Norte', 'code' => 'RN'],
            ['name' => 'Região Sul', 'code' => 'RS'],
            ['name' => 'Região Centro-Oeste', 'code' => 'RCO'],
        ];

        $regionalIds = [];
        foreach ($regionalUnits as $regional) {
            $regionalId = Uuid::uuid4()->toString();
            $regionalIds[] = $regionalId;
            
            DB::table('organization_units')->insert([
                'id' => $regionalId,
                'organization_id' => $organizationId,
                'name' => $regional['name'],
                'code' => $regional['code'],
                'type' => 'regional',
                'parent_id' => $companyUnitId,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create stores (Store Manager level)
        $stores = [
            ['name' => 'Loja Centro Norte', 'code' => 'LCN', 'regional' => 0],
            ['name' => 'Loja Shopping Norte', 'code' => 'LSN', 'regional' => 0],
            ['name' => 'Loja Centro Sul', 'code' => 'LCS', 'regional' => 1],
            ['name' => 'Loja Shopping Sul', 'code' => 'LSS', 'regional' => 1],
            ['name' => 'Loja Centro Oeste', 'code' => 'LCO', 'regional' => 2],
            ['name' => 'Loja Shopping CO', 'code' => 'LSCO', 'regional' => 2],
        ];

        foreach ($stores as $store) {
            DB::table('organization_units')->insert([
                'id' => Uuid::uuid4()->toString(),
                'organization_id' => $organizationId,
                'name' => $store['name'],
                'code' => $store['code'],
                'type' => 'store',
                'parent_id' => $regionalIds[$store['regional']],
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create departments
        $departments = [
            ['type' => 'administrative', 'name' => 'Administrativo', 'description' => 'Gestão administrativa geral'],
            ['type' => 'financial', 'name' => 'Financeiro', 'description' => 'Controle financeiro e contábil'],
            ['type' => 'marketing', 'name' => 'Marketing', 'description' => 'Marketing e comunicação'],
            ['type' => 'operations', 'name' => 'Operações', 'description' => 'Operações e logística'],
            ['type' => 'trade', 'name' => 'Trade', 'description' => 'Trade marketing e vendas'],
            ['type' => 'macro', 'name' => 'Macro', 'description' => 'Planejamento macro estratégico'],
        ];

        foreach ($departments as $department) {
            DB::table('departments')->insert([
                'id' => Uuid::uuid4()->toString(),
                'organization_id' => $organizationId,
                'type' => $department['type'],
                'name' => $department['name'],
                'description' => $department['description'],
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Organization structure created successfully!');
        $this->command->info('Organization ID: ' . $organizationId);
    }
}