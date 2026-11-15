<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('status');
            $table->timestamp('followup_sent_at')->nullable()->after('reminder_sent_at');
            $table->index(['status', 'reminder_sent_at', 'starts_at']);
            $table->index(['status', 'followup_sent_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['status', 'reminder_sent_at', 'starts_at']);
            $table->dropIndex(['status', 'followup_sent_at', 'ends_at']);
            $table->dropColumn(['reminder_sent_at', 'followup_sent_at']);
        });
    }
};
