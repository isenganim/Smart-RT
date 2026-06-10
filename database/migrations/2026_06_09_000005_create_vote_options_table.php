<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vote_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->timestamps();
            $table->unique(['vote_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_options');
    }
};
