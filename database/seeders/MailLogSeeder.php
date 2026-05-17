<?php

namespace Database\Seeders;

use App\Models\MailLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MailLogSeeder extends Seeder
{
    public function run(): void
    {
        $senders = [
            'newsletter@example.com',
            'alerts@company.com',
            'support@business.com',
        ];

        $recipients = [
            'user1@client.com', 'user2@client.com', 'user3@client.com',
            'user4@client.com', 'user5@client.com', 'user6@client.com',
            'user7@client.com', 'user8@client.com', 'user9@client.com',
            'user10@client.com',
        ];

        $subjects = [
            'Weekly Newsletter',
            'Account Alert',
            'Password Reset',
            'Order Confirmation',
            'Monthly Report',
            'System Notification',
            'Welcome Email',
            'Security Alert',
        ];

        $statuses = ['sent', 'sent', 'sent', 'sent', 'sent', 'deferred', 'bounced', 'failed'];

        // Generate 500 sample records over the last 30 days
        for ($i = 0; $i < 500; $i++) {
            $sender = $senders[array_rand($senders)];
            $daysAgo = rand(0, 30);
            $hoursAgo = rand(0, 23);
            $minutesAgo = rand(0, 59);

            MailLog::create([
                'sender' => $sender,
                'recipient' => $recipients[array_rand($recipients)],
                'subject' => $subjects[array_rand($subjects)],
                'message_id' => '<' . uniqid() . '@mail.example.com>',
                'status' => $statuses[array_rand($statuses)],
                'smtp_response' => '250 OK: queued as ' . uniqid(),
                'mail_at' => Carbon::now()
                    ->subDays($daysAgo)
                    ->subHours($hoursAgo)
                    ->subMinutes($minutesAgo),
            ]);
        }
    }
}