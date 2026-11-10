<?php

namespace Tests\Unit;

use App\Services\KeywordClassifier;
use PHPUnit\Framework\TestCase;

class KeywordClassifierTest extends TestCase
{
    public function test_classifies_billing_messages(): void
    {
        $result = (new KeywordClassifier)->classify('Please fix my invoice and payment charge');

        $this->assertSame('billing', $result['category']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_returns_unknown_for_unrelated_text(): void
    {
        $result = (new KeywordClassifier)->classify('Hello there');

        $this->assertSame('unknown', $result['category']);
    }
}
