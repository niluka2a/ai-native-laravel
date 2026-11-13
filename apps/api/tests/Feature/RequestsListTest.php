<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequestsListTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '2024-01-01 12:00:00';

    private function makeRequest(string $id, string $createdAt): void
    {
        DB::table('customer_requests')->insert([
            'id' => $id,
            'message' => "message {$id}",
            'status' => 'open',
            'category' => null,
            'confidence' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function makeNote(string $id, string $requestId, string $body, string $createdAt): void
    {
        DB::table('request_notes')->insert([
            'id' => $id,
            'body' => $body,
            'author_name' => 'tester',
            'request_id' => $requestId,
            'created_at' => $createdAt,
        ]);
    }

    private function at(int $seconds): string
    {
        return date('Y-m-d H:i:s', strtotime(self::BASE) + $seconds);
    }

    public function test_counts_and_latest_preview_with_notes_inserted_out_of_order(): void
    {
        $a = '00000000-0000-4000-8000-00000000000a';
        $this->makeRequest($a, $this->at(0));
        $this->makeNote('00000000-0000-4000-8000-000000000101', $a, 'newest', $this->at(2));
        $this->makeNote('00000000-0000-4000-8000-000000000102', $a, 'oldest', $this->at(0));
        $this->makeNote('00000000-0000-4000-8000-000000000103', $a, 'middle', $this->at(1));

        $item = $this->getJson('/requests')->assertOk()->json('0');

        $this->assertSame(3, $item['noteCount']);
        $this->assertSame('newest', $item['latestNotePreview']);
    }

    public function test_request_without_notes_has_zero_count_and_null_preview(): void
    {
        $this->makeRequest('00000000-0000-4000-8000-00000000000b', $this->at(0));

        $item = $this->getJson('/requests')->assertOk()->json('0');

        $this->assertSame(0, $item['noteCount']);
        $this->assertNull($item['latestNotePreview']);
    }

    public function test_items_are_ordered_by_created_at_desc_and_have_the_expected_keys(): void
    {
        $old = '00000000-0000-4000-8000-0000000000a1';
        $mid = '00000000-0000-4000-8000-0000000000a2';
        $new = '00000000-0000-4000-8000-0000000000a3';
        $this->makeRequest($mid, $this->at(10));
        $this->makeRequest($old, $this->at(0));
        $this->makeRequest($new, $this->at(20));

        $items = $this->getJson('/requests')->assertOk()->json();

        $this->assertSame([$new, $mid, $old], array_column($items, 'id'));
        $this->assertSame(
            ['id', 'message', 'status', 'category', 'confidence', 'noteCount', 'latestNotePreview', 'createdAt', 'updatedAt'],
            array_keys($items[0])
        );
    }

    public function test_notes_with_the_same_created_at_are_broken_by_greater_id(): void
    {
        $a = '00000000-0000-4000-8000-00000000000c';
        $this->makeRequest($a, $this->at(0));
        $this->makeNote('00000000-0000-4000-8000-000000000002', $a, 'second id', $this->at(5));
        $this->makeNote('00000000-0000-4000-8000-000000000001', $a, 'first id', $this->at(5));

        $item = $this->getJson('/requests')->assertOk()->json('0');

        $this->assertSame('second id', $item['latestNotePreview']);
    }

    public function test_query_count_does_not_grow_with_the_number_of_requests(): void
    {
        $seed = function (int $from, int $to): void {
            for ($i = $from; $i <= $to; $i++) {
                $id = sprintf('00000000-0000-4000-8000-%012d', $i);
                $this->makeRequest($id, $this->at($i));
                $this->makeNote(sprintf('00000000-0000-4000-9000-%012d', $i), $id, "note {$i}", $this->at($i));
            }
        };
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/requests')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $seed(1, 2);
        $small = $count();
        $seed(3, 25);
        $large = $count();

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(3, $large);
    }
}
