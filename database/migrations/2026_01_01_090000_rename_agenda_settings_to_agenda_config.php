<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agenda_settings') && ! Schema::hasTable('agenda_config')) {
            Schema::rename('agenda_settings', 'agenda_config');
        }

        if (! Schema::hasTable('agenda_config')) {
            return;
        }

        if (Schema::hasColumn('agenda_config', 'hora_abertura')) {
            DB::statement('ALTER TABLE agenda_config CHANGE hora_abertura opening_time TIME NOT NULL');
        }

        if (Schema::hasColumn('agenda_config', 'hora_fechamento')) {
            DB::statement('ALTER TABLE agenda_config CHANGE hora_fechamento closing_time TIME NOT NULL');
        }

        if (Schema::hasColumn('agenda_config', 'duracao_reserva_minutos')) {
            DB::statement('ALTER TABLE agenda_config CHANGE duracao_reserva_minutos slot_duration INT UNSIGNED NOT NULL');
        }

        if (Schema::hasColumn('agenda_config', 'dias_semana_ativos')) {
            DB::statement('ALTER TABLE agenda_config CHANGE dias_semana_ativos active_days JSON NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('agenda_config')) {
            return;
        }

        if (Schema::hasColumn('agenda_config', 'opening_time')) {
            DB::statement('ALTER TABLE agenda_config CHANGE opening_time hora_abertura TIME NOT NULL');
        }

        if (Schema::hasColumn('agenda_config', 'closing_time')) {
            DB::statement('ALTER TABLE agenda_config CHANGE closing_time hora_fechamento TIME NOT NULL');
        }

        if (Schema::hasColumn('agenda_config', 'slot_duration')) {
            DB::statement('ALTER TABLE agenda_config CHANGE slot_duration duracao_reserva_minutos INT UNSIGNED NOT NULL');
        }

        if (Schema::hasColumn('agenda_config', 'active_days')) {
            DB::statement('ALTER TABLE agenda_config CHANGE active_days dias_semana_ativos JSON NOT NULL');
        }

        if (! Schema::hasTable('agenda_settings')) {
            Schema::rename('agenda_config', 'agenda_settings');
        }
    }
};
