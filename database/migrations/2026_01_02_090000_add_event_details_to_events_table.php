<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('location')->after('end_time');
            $table->integer('max_people')->nullable()->after('location');
            $table->boolean('is_paid')->default(false)->after('visibility');
            $table->string('status')->default('ativo')->after('is_paid');
            $table->text('description')->nullable()->after('status');
            $table->foreignId('created_by')->nullable()->after('description')->constrained('users')->cascadeOnDelete();

            $table->index('status');
        });

        DB::table('events')
            ->where('visibility', 'public')
            ->update(['visibility' => 'publico']);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'name',
                'location',
                'max_people',
                'is_paid',
                'status',
                'description',
                'created_by',
            ]);
        });
    }
};
