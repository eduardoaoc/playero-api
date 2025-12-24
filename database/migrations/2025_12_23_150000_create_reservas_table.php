<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('quadra_id')->constrained('quadras')->cascadeOnDelete();
            $table->date('data');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->enum('status', [
                'pendente_pagamento',
                'confirmada',
                'cancelada',
                'expirada',
            ])->default('pendente_pagamento');
            $table->timestamps();

            $table->index(['quadra_id', 'data']);
            $table->index('status');
            $table->index(['hora_inicio', 'hora_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
