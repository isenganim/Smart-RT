<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreign('ronda_scan_session_id')->references('id')->on('ronda_scan_sessions')->nullOnDelete();
            $table->foreignId('reverses_id')->nullable()->after('ronda_scan_session_id')
                ->constrained('cash_transactions')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['ronda_scan_session_id']);
            $table->dropConstrainedForeignId('reverses_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
        });
    }
};
