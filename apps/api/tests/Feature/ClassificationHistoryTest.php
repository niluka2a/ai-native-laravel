<?php

namespace Tests\Feature;

use App\Contracts\ClassifierProvider;
use App\Data\ClassificationResult;
use App\Enums\Category;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Services\KeywordClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ClassificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(string $message = 'existing'): CustomerRequest
    {
        return CustomerRequest::create(['message' => $message, 'status' => 'open']);
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

    private function addHistory(CustomerRequest $request, Category $category, float $confidence = 0.86): RequestClassification
    {
        return $request->classifications()->create([
            'category' => $category,
            'confidence' => $confidence,
            'provider' => 'keyword',
        ]);
    }

    public function test_classify_with_request_id_writes_one_history_row(): void
    {
        $row = $this->makeRequest();

        $this->classify(['message' => 'invoice', 'requestId' => $row->id])
            ->assertOk()
            ->assertExactJson(['category' => 'billing', 'confidence' => 0.71, 'requestId' => $row->id]);

        $this->assertDatabaseCount('request_classifications', 1);
        $this->assertDatabaseHas('request_classifications', [
            'request_id' => $row->id,
            'category' => 'billing',
            'confidence' => 0.71,
            'provider' => 'keyword',
        ]);
    }

    public function test_classifying_twice_appends_history(): void
    {
        $row = $this->makeRequest();

        $this->classify(['message' => 'Please fix my invoice today', 'requestId' => $row->id])->assertOk();
        $this->classify(['message' => 'I would like a demo please', 'requestId' => $row->id])->assertOk();

        $this->assertSame(['billing', 'sales'], $row->classifications()->orderBy('id')->get()
            ->map(fn ($c) => $c->category->value)->all());
    }

    public function test_calls_that_store_nothing_write_no_history(): void
    {
        $row = $this->makeRequest();

        $this->classify(['message' => 'Please fix my invoice today'])->assertOk();
        $this->classify(['message' => 'invoice', 'requestId' => '00000000-0000-4000-8000-000000000999'])->assertNotFound();
        $this->classify(['message' => '', 'requestId' => $row->id])->assertStatus(422);
        $this->classify(['message' => 'x', 'requestId' => 'not-a-uuid'])->assertStatus(422);

        $this->assertDatabaseCount('request_classifications', 0);
    }

    public function test_container_resolves_the_keyword_classifier(): void
    {
        $this->assertInstanceOf(KeywordClassifier::class, $this->app->make(ClassifierProvider::class));
    }

    public function test_a_swapped_provider_is_used_and_recorded(): void
    {
        $this->app->instance(ClassifierProvider::class, new class implements ClassifierProvider
        {
            public function classify(string $message): ClassificationResult
            {
                return new ClassificationResult(Category::Sales, 0.9);
            }

            public function name(): string
            {
                return 'fake';
            }
        });
        $row = $this->makeRequest();

        $this->classify(['message' => 'anything at all here', 'requestId' => $row->id])
            ->assertOk()
            ->assertExactJson(['category' => 'sales', 'confidence' => 0.9, 'requestId' => $row->id]);

        $this->assertDatabaseHas('request_classifications', [
            'request_id' => $row->id,
            'category' => 'sales',
            'provider' => 'fake',
        ]);
    }

    public function test_history_is_newest_first_with_id_as_tie_break(): void
    {
        $row = $this->makeRequest();

        $this->travelTo(now()->subHour());
        $oldest = $this->addHistory($row, Category::Support);
        $this->travelBack();
        $sameSecondA = $this->addHistory($row, Category::Sales);
        $sameSecondB = $this->addHistory($row, Category::Billing);

        $ids = collect($this->getJson('/requests/history')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$sameSecondB->id, $sameSecondA->id, $oldest->id], $ids);
    }

    public function test_history_item_shape(): void
    {
        $row = $this->makeRequest('Please fix my invoice');
        $row->update(['status' => 'in_progress']);
        $entry = $this->addHistory($row, Category::Billing, 0.86);

        $item = $this->getJson('/requests/history')->assertOk()->json('data.0');

        $this->assertEqualsCanonicalizing([
            'id' => $entry->id,
            'requestId' => $row->id,
            'requestMessage' => 'Please fix my invoice',
            'requestStatus' => 'in_progress',
            'category' => 'billing',
            'confidence' => 0.86,
            'provider' => 'keyword',
            'createdAt' => $entry->created_at->toJSON(),
        ], $item);
    }

    public function test_history_filters_by_category(): void
    {
        $row = $this->makeRequest();
        $this->addHistory($row, Category::Billing);
        $this->addHistory($row, Category::Sales);
        $this->addHistory($row, Category::Billing);

        $categories = collect($this->getJson('/requests/history?category=billing')->assertOk()->json('data'))
            ->pluck('category')->all();

        $this->assertSame(['billing', 'billing'], $categories);
    }

    public function test_unknown_category_is_422_and_empty_category_returns_all(): void
    {
        $row = $this->makeRequest();
        $this->addHistory($row, Category::Billing);
        $this->addHistory($row, Category::Sales);

        $this->call('GET', '/requests/history', ['category' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
        $this->call('GET', '/requests/history', ['page' => '0'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('page');

        $this->getJson('/requests/history?category=')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_history_is_paginated_and_keeps_the_filter_in_links(): void
    {
        $row = $this->makeRequest();
        $now = now()->toDateTimeString();
        DB::table('request_classifications')->insert(array_map(fn () => [
            'request_id' => $row->id,
            'category' => 'billing',
            'confidence' => 0.86,
            'provider' => 'keyword',
            'created_at' => $now,
        ], range(1, 51)));

        $first = $this->getJson('/requests/history?category=billing')->assertOk();
        $first->assertJsonCount(50, 'data');
        $this->assertStringContainsString('category=billing', $first->json('links.next'));

        $this->getJson('/requests/history?category=billing&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('links.next', null);
    }

    public function test_history_runs_a_constant_number_of_queries(): void
    {
        $counts = [];
        foreach ([3, 10] as $requests) {
            for ($i = 0; $i < $requests; $i++) {
                $this->addHistory($this->makeRequest("message $i"), Category::Billing);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/requests/history')->assertOk();
            $counts[] = count(DB::getQueryLog());
        }

        $this->assertSame([2, 2], $counts);
    }
}
