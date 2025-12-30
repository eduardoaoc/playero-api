<?php

namespace Database\Seeders;

use App\Models\AgendaConfig;
use Illuminate\Database\Seeder;

class AgendaConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (AgendaConfig::query()->exists()) {
            return;
        }

        AgendaConfig::create([
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'slot_duration' => 60,
            'active_days' => [1, 2, 3, 4, 5, 6, 7],
            'timezone' => 'America/Sao_Paulo',
        ]);
    }
}
