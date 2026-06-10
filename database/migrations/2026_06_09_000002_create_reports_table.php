<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->text('description');
            $table->string('status')->default('baru');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
