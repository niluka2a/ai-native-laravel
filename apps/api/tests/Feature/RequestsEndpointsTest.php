<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\CustomerRequest;
use App\Models\RequestNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private const MISSING_ID = '00000000-0000-4000-8000-000000000000';

    private const SUMMARY_KEYS = ['id', 'message', 'status', 'statusLabel', 'category', 'confidence', 'createdAt', 'updatedAt'];

    private function makeRequest(string $status = 'open'): CustomerRequest
    {
        return CustomerRequest::create(['message' => 'My invoice is wrong', 'status' => $status]);
    }

    public function test_show_returns_the_request_with_its_notes_in_camel_case(): void
    {
        $row = $this->makeRequest();
        $note = RequestNote::create(['request_id' => $row->id, 'body' => 'Called back', 'author_name' => 'Sam']);

        $json = $this->getJson("/requests/{$row->id}")->assertOk()->json();

        $this->assertSame([...self::SUMMARY_KEYS, 'notes'], array_keys($json));
        $this->assertSame($row->id, $json['id']);
        $this->assertSame($row->created_at->toISOString(), $json['createdAt']);
        $this->assertSame([[
            'id' => $note->id,
            'body' => 'Called back',
            'authorName' => 'Sam',
            'createdAt' => $note->created_at->toISOString(),
        ]], $json['notes']);
    }

    public function test_show_returns_404_for_an_unknown_id(): void
    {
        $this->getJson('/requests/'.self::MISSING_ID)->assertNotFound();
    }

    public function test_store_creates_an_open_request_and_returns_201(): void
    {
        $json = $this->postJson('/requests', ['message' => 'Please send a demo'])
            ->assertCreated()
            ->json();

        $this->assertSame(self::SUMMARY_KEYS, array_keys($json));
        $this->assertSame('Please send a demo', $json['message']);
        $this->assertSame('open', $json['status']);
        $this->assertSame('Open', $json['statusLabel']);
        $this->assertNull($json['category']);
        $this->assertNull($json['confidence']);
        $this->assertDatabaseHas('customer_requests', ['id' => $json['id'], 'status' => 'open']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidStorePayloads(): array
    {
        return [
            'missing message' => [[]],
            'empty message' => [['message' => '']],
            'array message' => [['message' => ['hi']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidStorePayloads')]
    public function test_store_rejects_an_invalid_message_with_422(array $payload): void
    {
        $this->postJson('/requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('customer_requests', 0);
    }

    public function test_update_status_saves_and_returns_the_request_without_notes(): void
    {
        $row = $this->makeRequest();
        RequestNote::create(['request_id' => $row->id, 'body' => 'n', 'author_name' => 'Sam']);

        $json = $this->patchJson("/requests/{$row->id}/status", ['status' => 'resolved'])
            ->assertOk()
            ->json();

        $this->assertSame(self::SUMMARY_KEYS, array_keys($json));
        $this->assertSame('resolved', $json['status']);
        $this->assertSame('Resolved', $json['statusLabel']);
        $this->assertSame(RequestStatus::Resolved, $row->fresh()->status);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidStatusPayloads(): array
    {
        return [
            'missing status' => [[]],
            'unknown status' => [['status' => 'done']],
            'array status' => [['status' => ['open']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidStatusPayloads')]
    public function test_update_status_rejects_an_invalid_status_with_422(array $payload): void
    {
        $row = $this->makeRequest();

        $this->patchJson("/requests/{$row->id}/status", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(RequestStatus::Open, $row->fresh()->status);
    }

    public function test_update_status_returns_404_for_an_unknown_id(): void
    {
        $this->patchJson('/requests/'.self::MISSING_ID.'/status', ['status' => 'resolved'])
            ->assertNotFound();
    }
}
