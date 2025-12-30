<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE agenda_exceptions MODIFY hora_abertura TIME NULL');
        DB::statement('ALTER TABLE agenda_exceptions MODIFY hora_fechamento TIME NULL');
        DB::statement('ALTER TABLE agenda_exceptions MODIFY open_time TIME NULL');
        DB::statement('ALTER TABLE agenda_exceptions MODIFY close_time TIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE agenda_exceptions MODIFY hora_abertura TIME NOT NULL');
        DB::statement('ALTER TABLE agenda_exceptions MODIFY hora_fechamento TIME NOT NULL');
        DB::statement('ALTER TABLE agenda_exceptions MODIFY open_time TIME NOT NULL');
        DB::statement('ALTER TABLE agenda_exceptions MODIFY close_time TIME NOT NULL');
    }
};
