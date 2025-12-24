<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_section_id')->constrained('cms_sections')->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->enum('type', ['text', 'image', 'textarea'])->default('text');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['cms_section_id', 'key']);
            $table->index(['type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_fields');
    }
};
