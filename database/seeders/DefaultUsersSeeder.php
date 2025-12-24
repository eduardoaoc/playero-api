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
                'email' => 'superadmin@playero.com',
                'role' => Role::SUPER_ADMIN,
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@playero.com',
                'role' => Role::ADMIN,
            ],
            [
                'name' => 'Cliente',
                'email' => 'cliente@playero.com',
                'role' => Role::CLIENTE,
            ],
        ];

        foreach ($defaults as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
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
