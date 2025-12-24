<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quadras', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('tipo');
            $table->boolean('ativa')->default(true);
            $table->integer('ordem')->nullable();
            $table->integer('capacidade')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quadras');
    }
};
