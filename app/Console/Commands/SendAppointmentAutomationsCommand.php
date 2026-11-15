<?php

namespace App\Console\Commands;

use App\Actions\SendAppointmentAutomations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:send-automations {--limit=100 : Maximum reminders and follow-ups to inspect per run}')]
#[Description('Send due WhatsApp appointment reminders and follow-ups')]
class SendAppointmentAutomationsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SendAppointmentAutomations $sendAutomations): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $result = $sendAutomations->handle($limit);

        $this->info("Sent {$result['sent']} appointment messages; {$result['failed']} failed.");

        return self::SUCCESS;
    }
}
