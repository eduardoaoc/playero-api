<?php

namespace App\Support;

use App\Models\User;

class UserPresenter
{
    public static function make(User $user): array
    {
        $roleNames = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->values()->all()
            : $user->roleNames();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'role' => $roleNames[0] ?? null,
            'roles' => $roleNames,
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
