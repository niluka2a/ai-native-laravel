<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassifyRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(string $status = 'open'): CustomerRequest
    {
        return CustomerRequest::create([
            'message' => 'existing',
            'status' => $status,
            'category' => null,
            'confidence' => null,
        ]);
    }

    /**
     * Posts a JSON body without an Accept header, like the web client does.
     *
     * @param  array<string, mixed>  $body
     */
    private function classify(array $body): TestResponse
    {
        return $this->call(
            'POST',
            '/requests/classify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($body),
        );
    }

    /**
     * @return array<string, array{string, string, float}>
     */
    public static function longMessages(): array
    {
        return [
            'billing' => ['Please fix my invoice today', 'billing', 0.86],
            'sales' => ['I would like a demo please', 'sales', 0.8],
            'support' => ['Something is broken again today', 'support', 0.78],
            'unknown' => ['Hello there my friend', 'unknown', 0.4],
        ];
    }

    #[DataProvider('longMessages')]
    public function test_long_messages_keep_their_confidence(string $message, string $category, float $confidence): void
    {
        $this->classify(['message' => $message])
            ->assertOk()
            ->assertExactJson(['category' => $category, 'confidence' => $confidence, 'requestId' => null]);
    }

    /**
     * @return array<string, array{string, string, float}>
     */
    public static function shortMessages(): array
    {
        return [
            'billing' => ['invoice', 'billing', 0.71],
            'sales' => ['demo', 'sales', 0.65],
            'support' => ['broken', 'support', 0.63],
            'unknown is not softened' => ['hello', 'unknown', 0.4],
            'two words' => ['my invoice', 'billing', 0.71],
        ];
    }

    #[DataProvider('shortMessages')]
    public function test_short_messages_are_softened(string $message, string $category, float $confidence): void
    {
        $this->classify(['message' => $message])
            ->assertOk()
            ->assertExactJson(['category' => $category, 'confidence' => $confidence, 'requestId' => null]);
    }

    public function test_three_words_are_not_softened(): void
    {
        $this->classify(['message' => 'my invoice please'])
            ->assertOk()
            ->assertExactJson(['category' => 'billing', 'confidence' => 0.86, 'requestId' => null]);
    }

    public function test_open_request_is_saved_and_moves_to_in_progress(): void
    {
        $row = $this->makeRequest('open');

        $this->classify(['message' => 'Please fix my invoice today', 'requestId' => $row->id])
            ->assertOk()
            ->assertExactJson(['category' => 'billing', 'confidence' => 0.86, 'requestId' => $row->id]);

        $row->refresh();
        $this->assertSame('billing', $row->category);
        $this->assertSame(0.86, $row->confidence);
        $this->assertSame(RequestStatus::InProgress, $row->status);
    }

    public function test_resolved_request_keeps_its_status(): void
    {
        $row = $this->makeRequest('resolved');

        $this->classify(['message' => 'I would like a demo please', 'requestId' => $row->id])->assertOk();

        $row->refresh();
        $this->assertSame('sales', $row->category);
        $this->assertSame(RequestStatus::Resolved, $row->status);
    }

    public function test_unknown_result_is_saved_too(): void
    {
        $row = $this->makeRequest('open');

        $this->classify(['message' => 'Hello there my friend', 'requestId' => $row->id])->assertOk();

        $row->refresh();
        $this->assertSame('unknown', $row->category);
        $this->assertSame(0.4, $row->confidence);
        $this->assertSame(RequestStatus::InProgress, $row->status);
    }

    public function test_without_request_id_nothing_is_written(): void
    {
        $row = $this->makeRequest('open');

        $this->classify(['message' => 'Please fix my invoice today'])->assertOk();

        $row->refresh();
        $this->assertNull($row->category);
        $this->assertSame(RequestStatus::Open, $row->status);
    }

    public function test_unknown_request_id_is_404(): void
    {
        $this->classify(['message' => 'invoice', 'requestId' => '00000000-0000-4000-8000-000000000999'])
            ->assertNotFound();
    }

    public function test_accepts_2000_characters(): void
    {
        $this->classify(['message' => str_repeat('a', 2000)])->assertOk();
    }

    public function test_classify_with_request_id_runs_three_queries(): void
    {
        // Select, update and history insert. If the result does not change the row,
        // Eloquent skips the update and it is 2.
        $row = $this->makeRequest('open');

        DB::enableQueryLog();
        $this->classify(['message' => 'Please fix my invoice today', 'requestId' => $row->id])->assertOk();

        $this->assertCount(3, DB::getQueryLog());
    }

    public function test_accepts_zero_as_a_message(): void
    {
        $this->classify(['message' => '0'])
            ->assertOk()
            ->assertExactJson(['category' => 'unknown', 'confidence' => 0.4, 'requestId' => null]);
    }

    public function test_accepts_2000_multibyte_characters(): void
    {
        $this->classify(['message' => str_repeat('é', 2000)])->assertOk();
        $this->classify(['message' => str_repeat('é', 2001)])->assertStatus(422);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function invalidBodies(): array
    {
        return [
            'missing message' => [[], 'message'],
            'blank message' => [['message' => '   '], 'message'],
            'empty message' => [['message' => ''], 'message'],
            'non-string message' => [['message' => 123], 'message'],
            'array message' => [['message' => ['a']], 'message'],
            'too long message' => [['message' => str_repeat('a', 2001)], 'message'],
            'malformed requestId' => [['message' => 'x', 'requestId' => 'not-a-uuid'], 'requestId'],
            'array requestId' => [['message' => 'x', 'requestId' => ['a']], 'requestId'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('invalidBodies')]
    public function test_invalid_input_returns_422_json_without_accept_header(array $body, string $field): void
    {
        $this->classify($body)
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors($field);
    }
}
