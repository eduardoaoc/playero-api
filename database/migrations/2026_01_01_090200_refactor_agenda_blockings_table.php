<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agenda_blockings')) {
            return;
        }

        if (Schema::hasColumn('agenda_blockings', 'data') && ! Schema::hasColumn('agenda_blockings', 'date')) {
            DB::statement('ALTER TABLE agenda_blockings CHANGE data date DATE NOT NULL');
        }

        if (Schema::hasColumn('agenda_blockings', 'hora_inicio') && ! Schema::hasColumn('agenda_blockings', 'start_time')) {
            DB::statement('ALTER TABLE agenda_blockings CHANGE hora_inicio start_time TIME NULL');
        }

        if (Schema::hasColumn('agenda_blockings', 'hora_fim') && ! Schema::hasColumn('agenda_blockings', 'end_time')) {
            DB::statement('ALTER TABLE agenda_blockings CHANGE hora_fim end_time TIME NULL');
        }

        if (Schema::hasColumn('agenda_blockings', 'motivo') && ! Schema::hasColumn('agenda_blockings', 'reason')) {
            DB::statement('ALTER TABLE agenda_blockings CHANGE motivo reason VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('agenda_blockings')) {
            return;
        }

        if (Schema::hasColumn('agenda_blockings', 'reason') && ! Schema::hasColumn('agenda_blockings', 'motivo')) {
            DB::statement('ALTER TABLE agenda_blockings CHANGE reason motivo VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('agenda_blockings', 'end_time') && ! Schema::hasColumn('agenda_blockings', 'hora_fim')) {
            DB::statement('ALTER TABLE agenda_blockings CHANGE end_time hora_fim TIME NULL');
        }

        if (Schema::hasColumn('agenda_blockings', 'start_time') && ! Schema::hasColumn('agenda_blockings', 'hora_inicio')) {
            DB::statement('ALTER TABLE agenda_blockings CHANGE start_time hora_inicio TIME NULL');
        }

        if (Schema::hasColumn('agenda_blockings', 'date') && ! Schema::hasColumn('agenda_blockings', 'data')) {
            DB::statement('ALTER TABLE agenda_blockings CHANGE date data DATE NOT NULL');
        }
    }
};
