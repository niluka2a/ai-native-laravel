<?php

namespace App\Services;

use App\Contracts\ClassifierProvider;
use App\Data\ClassificationResult;
use App\Enums\Category;

/**
 * Deterministic keyword classifier. The local default ClassifierProvider; a
 * future LLM provider can replace it through the container binding.
 */
class KeywordClassifier implements ClassifierProvider
{
    public function name(): string
    {
        return 'keyword';
    }

    public function classify(string $message): ClassificationResult
    {
        $text = mb_strtolower($message);

        if (preg_match('/bill|invoice|payment|refund|charge/', $text)) {
            return new ClassificationResult(Category::Billing, 0.86);
        }
        if (preg_match('/buy|pricing|demo|sales|upgrade|plan/', $text)) {
            return new ClassificationResult(Category::Sales, 0.8);
        }
        if (preg_match('/help|broken|error|issue|support|bug/', $text)) {
            return new ClassificationResult(Category::Support, 0.78);
        }

        return new ClassificationResult(Category::Unknown, 0.4);
    }
}
