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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('reminder_enabled')->default(true)->after('buffer_minutes');
            $table->unsignedSmallInteger('reminder_hours_before')->default(24)->after('reminder_enabled');
            $table->text('reminder_message')->nullable()->after('reminder_hours_before');
            $table->boolean('followup_enabled')->default(true)->after('reminder_message');
            $table->unsignedSmallInteger('followup_hours_after')->default(2)->after('followup_enabled');
            $table->text('followup_message')->nullable()->after('followup_hours_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_enabled',
                'reminder_hours_before',
                'reminder_message',
                'followup_enabled',
                'followup_hours_after',
                'followup_message',
            ]);
        });
    }
};
