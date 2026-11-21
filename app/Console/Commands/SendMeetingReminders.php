<?php

namespace App\Console\Commands;

use App\Services\MeetingService;
use Illuminate\Console\Command;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';

    protected $description = 'Notify attendees of meetings whose reminder time has come';

    public function handle(MeetingService $meetings): int
    {
        $count = $meetings->sendDueReminders();
        $this->info("Sent reminders for {$count} meeting(s).");

        return self::SUCCESS;
    }
}
