<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private const MESSAGES = [
        'I need help changing my payment method',
        'Our billing invoice looks wrong this month',
        'Can someone walk me through the sales pricing tiers?',
        'The dashboard is broken after the last deploy',
        'Interested in a demo for our salon booking flow',
        'Refund did not arrive after cancellation',
        'Support ticket: login error on Safari',
        'Looking to upgrade our plan next quarter',
    ];

    private const NOTE_BODIES = [
        'Customer called in.',
        'Waiting on screenshot.',
        'Followed up by email.',
        'Escalated to billing ops.',
        'Reproduced locally.',
    ];

    private const REQUEST_COUNT = 1200;
    private const INSERT_CHUNK = 500;

    public function run(): void
    {
        $count = DB::table('customer_requests')->count();
        if ($count >= self::REQUEST_COUNT) {
            $this->command?->info("Seed skipped ({$count} requests already present)");

            return;
        }

        $now = Carbon::now();

        // Timestamps are staggered so ordering by created_at is deterministic.
        $requestRows = [];
        for ($i = 0; $i < self::REQUEST_COUNT; $i++) {
            $createdAt = $now->copy()->subMinutes(self::REQUEST_COUNT - $i);
            $requestRows[] = [
                'id' => (string) Str::uuid(),
                'message' => self::MESSAGES[$i % count(self::MESSAGES)].' (#'.($i + 1).')',
                'status' => $i % 5 === 0 ? 'resolved' : ($i % 3 === 0 ? 'in_progress' : 'open'),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($requestRows, self::INSERT_CHUNK) as $chunk) {
            DB::table('customer_requests')->insert($chunk);
        }

        $noteRows = [];
        foreach ($requestRows as $i => $request) {
            $noteCount = 3 + ($i % 3);
            for ($n = 0; $n < $noteCount; $n++) {
                $noteRows[] = [
                    'id' => (string) Str::uuid(),
                    'request_id' => $request['id'],
                    'body' => self::NOTE_BODIES[$n % count(self::NOTE_BODIES)].' ['.($i + 1).'.'.($n + 1).']',
                    'author_name' => $n % 2 === 0 ? 'Alex' : 'Sam',
                    'created_at' => $request['created_at']->copy()->addSeconds($n),
                ];
            }
        }

        foreach (array_chunk($noteRows, self::INSERT_CHUNK) as $chunk) {
            DB::table('request_notes')->insert($chunk);
        }

        $this->command?->info(sprintf('Seed complete (%d requests, %d notes)', count($requestRows), count($noteRows)));
    }
}
