<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::whereIn('name', Role::ALL)->get()->keyBy('name');

        $defaults = [
            [
                'name' => 'Super Admin',
                'last_name' => 'Admin',
                'email' => 'superadmin@playero.com',
                'role' => Role::SUPER_ADMIN,
            ],
        ];

        foreach ($defaults as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'last_name' => $data['last_name'],
                    'password' => Hash::make('12345678'),
                    'is_active' => true,
                ]
            );

            $role = $roles->get($data['role']);
            if ($role) {
                $user->roles()->sync([$role->id]);
            }
        }
    }
}
