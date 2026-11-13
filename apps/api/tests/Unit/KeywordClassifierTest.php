<?php

namespace Tests\Unit;

use App\Enums\Category;
use App\Services\KeywordClassifier;
use PHPUnit\Framework\TestCase;

class KeywordClassifierTest extends TestCase
{
    public function test_classifies_billing_messages(): void
    {
        $result = (new KeywordClassifier)->classify('Please fix my invoice and payment charge');

        $this->assertSame(Category::Billing, $result->category);
        $this->assertSame(0.86, $result->confidence);
    }

    public function test_returns_unknown_for_unrelated_text(): void
    {
        $result = (new KeywordClassifier)->classify('Hello there');

        $this->assertSame(Category::Unknown, $result->category);
        $this->assertSame(0.4, $result->confidence);
    }

    public function test_billing_beats_sales_beats_support(): void
    {
        $classifier = new KeywordClassifier;

        $this->assertSame(Category::Billing, $classifier->classify('invoice demo help')->category);
        $this->assertSame(Category::Sales, $classifier->classify('demo help')->category);
        $this->assertSame(Category::Support, $classifier->classify('help')->category);
    }
}
