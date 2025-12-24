<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::ALL as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
    }
}
