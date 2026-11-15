<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
        });

        DB::table('conversations')->orderBy('id')->eachById(function (object $conversation): void {
            $phone = preg_replace('/\D+/', '', $conversation->customer_wa_id) ?: $conversation->customer_wa_id;
            $customerId = DB::table('customers')->insertGetId([
                'workspace_id' => $conversation->workspace_id,
                'name' => $conversation->customer_name,
                'phone' => $phone,
                'status' => $conversation->status === 'booked' ? 'booked' : 'new',
                'source' => 'whatsapp',
                'last_contact_at' => $conversation->last_message_at,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
            ]);

            DB::table('conversations')->where('id', $conversation->id)->update(['customer_id' => $customerId]);
        });

        DB::table('appointments')->orderBy('id')->eachById(function (object $appointment): void {
            $phone = preg_replace('/\D+/', '', $appointment->customer_phone) ?: $appointment->customer_phone;
            $customer = DB::table('customers')
                ->where('workspace_id', $appointment->workspace_id)
                ->where('phone', $phone)
                ->first();

            if ($customer === null) {
                $customerId = DB::table('customers')->insertGetId([
                    'workspace_id' => $appointment->workspace_id,
                    'name' => $appointment->customer_name,
                    'phone' => $phone,
                    'email' => $appointment->customer_email,
                    'status' => $appointment->status === 'cancelled' ? 'lead' : 'booked',
                    'source' => 'appointment',
                    'last_contact_at' => $appointment->created_at,
                    'created_at' => $appointment->created_at,
                    'updated_at' => $appointment->updated_at,
                ]);
            } else {
                $customerId = $customer->id;
                DB::table('customers')->where('id', $customerId)->update([
                    'name' => $appointment->customer_name ?: $customer->name,
                    'email' => $appointment->customer_email ?: $customer->email,
                    'status' => $appointment->status === 'cancelled' ? $customer->status : 'booked',
                    'updated_at' => $appointment->updated_at,
                ]);
            }

            DB::table('appointments')->where('id', $appointment->id)->update(['customer_id' => $customerId]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
