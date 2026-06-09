<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('purpose');
            $table->string('status')->default('diajukan');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_requests');
    }
};
