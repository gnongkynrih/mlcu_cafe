<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //CREATE ROLES
        $adminRole = Role::create(['name' => 'admin']);
        $managerRole = Role::create(['name' => 'manager']);

        //CREATE PERMISSION
        $managerPermission = Permission::create(['name' => 'take order']);
        $adminPermission = Permission::create(['name' => 'administrator']);

        //Assign A Permission To A Role
        $adminRole->givePermissionTo($adminPermission);
        $adminRole->givePermissionTo($managerPermission);

        $managerRole->givePermissionTo($managerPermission);

        //ASSIGN THE ADMIN ROLE TO test user
        $user = \App\Models\User::where('email','test@test.com')->first();
        $user->assignRole('admin');

    }
}
