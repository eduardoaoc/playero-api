<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('data')->unique();
            $table->time('hora_abertura');
            $table->time('hora_fechamento');
            $table->date('date')->unique();
            $table->time('open_time');
            $table->time('close_time');
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_exceptions');
    }
};
