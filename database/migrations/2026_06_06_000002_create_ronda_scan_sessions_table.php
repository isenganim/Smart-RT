<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ronda_scan_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('pin', 6);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('pin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ronda_scan_sessions');
    }
};
