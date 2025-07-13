<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        // Get organization and units
        $organization = DB::table('organizations')->where('code', 'MADNEZZ')->first();
        if (!$organization) {
            $this->command->error('Organization not found. Run OrganizationSeeder first.');
            return;
        }

        $companyUnit = DB::table('organization_units')
            ->where('organization_id', $organization->id)
            ->where('type', 'company')
            ->first();

        $regionalUnits = DB::table('organization_units')
            ->where('organization_id', $organization->id)
            ->where('type', 'regional')
            ->get();

        $stores = DB::table('organization_units')
            ->where('organization_id', $organization->id)
            ->where('type', 'store')
            ->get();

        $departments = DB::table('departments')
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('type');

        // Get or create test users
        $users = $this->createTestUsers();

        // Create GO position
        $goPositionId = Uuid::uuid4()->toString();
        DB::table('positions')->insert([
            'id' => $goPositionId,
            'organization_id' => $organization->id,
            'organization_unit_id' => $companyUnit->id,
            'user_id' => $users['go']->id,
            'level' => 'go',
            'title' => 'Diretor Geral',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign all departments to GO
        foreach ($departments as $department) {
            DB::table('position_departments')->insert([
                'position_id' => $goPositionId,
                'department_id' => $department->id,
            ]);
        }

        // Create GR positions
        $grCount = 0;
        foreach ($regionalUnits as $regionalUnit) {
            $grPositionId = Uuid::uuid4()->toString();
            $grUserKey = 'gr' . ($grCount + 1);
            
            DB::table('positions')->insert([
                'id' => $grPositionId,
                'organization_id' => $organization->id,
                'organization_unit_id' => $regionalUnit->id,
                'user_id' => $users[$grUserKey]->id,
                'level' => 'gr',
                'title' => 'Gerente Regional - ' . $regionalUnit->name,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign operational departments to GR
            $grDepartments = ['operations', 'trade', 'marketing', 'administrative'];
            foreach ($grDepartments as $deptType) {
                if (isset($departments[$deptType])) {
                    DB::table('position_departments')->insert([
                        'position_id' => $grPositionId,
                        'department_id' => $departments[$deptType]->id,
                    ]);
                }
            }
            
            $grCount++;
        }

        // Create Store Manager positions
        $storeCount = 0;
        foreach ($stores as $store) {
            $smPositionId = Uuid::uuid4()->toString();
            $smUserKey = 'sm' . ($storeCount + 1);
            
            DB::table('positions')->insert([
                'id' => $smPositionId,
                'organization_id' => $organization->id,
                'organization_unit_id' => $store->id,
                'user_id' => $users[$smUserKey]->id,
                'level' => 'store_manager',
                'title' => 'Gerente de Loja - ' . $store->name,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign store-level departments
            $smDepartments = ['operations', 'trade'];
            foreach ($smDepartments as $deptType) {
                if (isset($departments[$deptType])) {
                    DB::table('position_departments')->insert([
                        'position_id' => $smPositionId,
                        'department_id' => $departments[$deptType]->id,
                    ]);
                }
            }
            
            $storeCount++;
        }

        $this->command->info('Positions created successfully!');
        $this->command->info('Test users created with hierarchy positions:');
        $this->command->info('- GO: go@madnezz.com (password: password)');
        $this->command->info('- GR1: gr1@madnezz.com (password: password)');
        $this->command->info('- GR2: gr2@madnezz.com (password: password)');
        $this->command->info('- GR3: gr3@madnezz.com (password: password)');
        $this->command->info('- SM1-6: sm1@madnezz.com to sm6@madnezz.com (password: password)');
    }

    private function createTestUsers(): array
    {
        $users = [];
        
        // Create GO user
        $goUserId = Uuid::uuid4()->toString();
        DB::table('users_ddd')->updateOrInsert(
            ['email' => 'go@madnezz.com'],
            [
                'id' => $goUserId,
                'name' => 'Diretor Geral',
                'email' => 'go@madnezz.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $users['go'] = (object)['id' => $goUserId];

        // Create GR users
        for ($i = 1; $i <= 3; $i++) {
            $grUserId = Uuid::uuid4()->toString();
            DB::table('users_ddd')->updateOrInsert(
                ['email' => "gr{$i}@madnezz.com"],
                [
                    'id' => $grUserId,
                    'name' => "Gerente Regional {$i}",
                    'email' => "gr{$i}@madnezz.com",
                    'email_verified_at' => now(),
                    'password' => bcrypt('password'),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $users["gr{$i}"] = (object)['id' => $grUserId];
        }

        // Create Store Manager users
        for ($i = 1; $i <= 6; $i++) {
            $smUserId = Uuid::uuid4()->toString();
            DB::table('users_ddd')->updateOrInsert(
                ['email' => "sm{$i}@madnezz.com"],
                [
                    'id' => $smUserId,
                    'name' => "Gerente Loja {$i}",
                    'email' => "sm{$i}@madnezz.com",
                    'email_verified_at' => now(),
                    'password' => bcrypt('password'),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $users["sm{$i}"] = (object)['id' => $smUserId];
        }

        return $users;
    }
}