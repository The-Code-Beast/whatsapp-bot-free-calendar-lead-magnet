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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whats_app_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_wa_id');
            $table->string('customer_name')->nullable();
            $table->string('status')->default('active');
            $table->string('booking_stage')->default('collecting');
            $table->boolean('human_takeover')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'customer_wa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
