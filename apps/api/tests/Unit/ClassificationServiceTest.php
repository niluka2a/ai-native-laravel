<?php

namespace Tests\Unit;

use App\Contracts\ClassifierProvider;
use App\Data\ClassificationResult;
use App\Enums\Category;
use App\Services\ClassificationService;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClassificationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function serviceReturning(Category $category, float $confidence): ClassificationService
    {
        $classifier = Mockery::mock(ClassifierProvider::class);
        $classifier->shouldReceive('classify')->andReturn(new ClassificationResult($category, $confidence));

        return new ClassificationService($classifier);
    }

    /**
     * @return array<string, array{float, Category, float}>
     */
    public static function thresholds(): array
    {
        return [
            'exactly 0.55 keeps the category' => [0.55, Category::Billing, 0.55],
            'below 0.55 becomes unknown, confidence kept' => [0.549, Category::Unknown, 0.549],
        ];
    }

    #[DataProvider('thresholds')]
    public function test_unknown_threshold(float $confidence, Category $expected, float $expectedConfidence): void
    {
        $result = $this->serviceReturning(Category::Billing, $confidence)->classify('one two three');

        $this->assertSame($expected, $result->category);
        $this->assertSame($expectedConfidence, $result->confidence);
    }

    public function test_two_words_are_softened_and_three_are_not(): void
    {
        $service = $this->serviceReturning(Category::Billing, 0.86);

        $this->assertSame(0.71, $service->classify('one two')->confidence);
        $this->assertSame(0.86, $service->classify('one two three')->confidence);
        $this->assertSame(0.86, $service->classify('one two three four five')->confidence);
    }

    public function test_softening_never_goes_below_the_floor(): void
    {
        $result = $this->serviceReturning(Category::Support, 0.6)->classify('help');

        // max(0.5, 0.6 - 0.15) = 0.5, which is below the threshold, so unknown.
        $this->assertSame(Category::Unknown, $result->category);
        $this->assertSame(0.5, $result->confidence);
    }

    public function test_unknown_is_not_softened(): void
    {
        $result = $this->serviceReturning(Category::Unknown, 0.4)->classify('hi');

        $this->assertSame(Category::Unknown, $result->category);
        $this->assertSame(0.4, $result->confidence);
    }

    public function test_message_is_trimmed_before_counting_words(): void
    {
        $result = $this->serviceReturning(Category::Billing, 0.86)->classify("  invoice \n");

        $this->assertSame(0.71, $result->confidence);
    }
}
