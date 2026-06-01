<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ronda_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ronda_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique(['ronda_schedule_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ronda_assignments');
    }
};
