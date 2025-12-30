<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_exceptions', function (Blueprint $table) {
            $table->boolean('fechado')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('agenda_exceptions', function (Blueprint $table) {
            $table->dropColumn('fechado');
        });
    }
};
