<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_settings', function (Blueprint $table) {
            $table->id();
            $table->time('hora_abertura');
            $table->time('hora_fechamento');
            $table->unsignedInteger('duracao_reserva_minutos');
            $table->json('dias_semana_ativos');
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_settings');
    }
};
