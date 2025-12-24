<?php

namespace App\Policies;

use App\Models\Reserva;
use App\Models\Role;
use App\Models\User;

class ReservaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN]);
    }

    public function view(User $user, Reserva $reserva): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])
            || $reserva->user_id === $user->id;
    }

    public function cancel(User $user, Reserva $reserva): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])
            || $reserva->user_id === $user->id;
    }
}
