<?php

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('condition', array_column(ItemCondition::cases(), 'value'))
                ->default(ItemCondition::BAIK->value);
            $table->enum('status', array_column(ItemStatus::cases(), 'value'))
                ->default(ItemStatus::TERSEDIA->value)
                ->index();
            $table->string('location')->nullable();
            $table->string('holder')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
