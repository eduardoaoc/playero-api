<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agenda_exceptions')) {
            return;
        }

        if (Schema::hasColumn('agenda_exceptions', 'data') && Schema::hasColumn('agenda_exceptions', 'date')) {
            DB::statement('UPDATE agenda_exceptions SET date = COALESCE(date, data)');

            try {
                DB::statement('ALTER TABLE agenda_exceptions DROP INDEX agenda_exceptions_data_unique');
            } catch (\Throwable $exception) {
            }

            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->dropColumn('data');
            });
        } elseif (Schema::hasColumn('agenda_exceptions', 'data') && ! Schema::hasColumn('agenda_exceptions', 'date')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE data date DATE NOT NULL');
        } elseif (! Schema::hasColumn('agenda_exceptions', 'date')) {
            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->date('date')->unique();
            });
        }

        if (Schema::hasColumn('agenda_exceptions', 'open_time') && ! Schema::hasColumn('agenda_exceptions', 'opening_time')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE open_time opening_time TIME NULL');
        } elseif (! Schema::hasColumn('agenda_exceptions', 'opening_time')) {
            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->time('opening_time')->nullable();
            });
        }

        if (Schema::hasColumn('agenda_exceptions', 'close_time') && ! Schema::hasColumn('agenda_exceptions', 'closing_time')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE close_time closing_time TIME NULL');
        } elseif (! Schema::hasColumn('agenda_exceptions', 'closing_time')) {
            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->time('closing_time')->nullable();
            });
        }

        if (Schema::hasColumn('agenda_exceptions', 'hora_abertura')) {
            DB::statement('UPDATE agenda_exceptions SET opening_time = COALESCE(opening_time, hora_abertura)');

            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->dropColumn('hora_abertura');
            });
        }

        if (Schema::hasColumn('agenda_exceptions', 'hora_fechamento')) {
            DB::statement('UPDATE agenda_exceptions SET closing_time = COALESCE(closing_time, hora_fechamento)');

            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->dropColumn('hora_fechamento');
            });
        }

        if (Schema::hasColumn('agenda_exceptions', 'open_time') && Schema::hasColumn('agenda_exceptions', 'opening_time')) {
            DB::statement('UPDATE agenda_exceptions SET opening_time = COALESCE(opening_time, open_time)');

            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->dropColumn('open_time');
            });
        }

        if (Schema::hasColumn('agenda_exceptions', 'close_time') && Schema::hasColumn('agenda_exceptions', 'closing_time')) {
            DB::statement('UPDATE agenda_exceptions SET closing_time = COALESCE(closing_time, close_time)');

            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->dropColumn('close_time');
            });
        }

        if (Schema::hasColumn('agenda_exceptions', 'fechado') && ! Schema::hasColumn('agenda_exceptions', 'is_closed')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE fechado is_closed TINYINT(1) NOT NULL DEFAULT 0');
        } elseif (! Schema::hasColumn('agenda_exceptions', 'is_closed')) {
            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->boolean('is_closed')->default(false);
            });
        }

        if (Schema::hasColumn('agenda_exceptions', 'motivo') && ! Schema::hasColumn('agenda_exceptions', 'reason')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE motivo reason VARCHAR(255) NULL');
        } elseif (! Schema::hasColumn('agenda_exceptions', 'reason')) {
            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->string('reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('agenda_exceptions')) {
            return;
        }

        if (Schema::hasColumn('agenda_exceptions', 'reason') && ! Schema::hasColumn('agenda_exceptions', 'motivo')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE reason motivo VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('agenda_exceptions', 'is_closed') && ! Schema::hasColumn('agenda_exceptions', 'fechado')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE is_closed fechado TINYINT(1) NOT NULL DEFAULT 0');
        }

        if (Schema::hasColumn('agenda_exceptions', 'opening_time') && ! Schema::hasColumn('agenda_exceptions', 'open_time')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE opening_time open_time TIME NULL');
        }

        if (Schema::hasColumn('agenda_exceptions', 'closing_time') && ! Schema::hasColumn('agenda_exceptions', 'close_time')) {
            DB::statement('ALTER TABLE agenda_exceptions CHANGE closing_time close_time TIME NULL');
        }

        if (! Schema::hasColumn('agenda_exceptions', 'data') && Schema::hasColumn('agenda_exceptions', 'date')) {
            Schema::table('agenda_exceptions', function (Blueprint $table) {
                $table->date('data')->unique();
            });

            DB::statement('UPDATE agenda_exceptions SET data = date');
        }
    }
};
