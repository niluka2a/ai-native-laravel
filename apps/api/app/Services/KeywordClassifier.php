<?php

namespace App\Services;

/**
 * Deterministic keyword classifier. Candidates may introduce a provider
 * interface and swap implementations (including a future LLM provider).
 */
class KeywordClassifier
{
    /**
     * @return array{category: 'support'|'sales'|'billing'|'unknown', confidence: float}
     */
    public function classify(string $message): array
    {
        $text = mb_strtolower($message);

        if (preg_match('/bill|invoice|payment|refund|charge/', $text)) {
            return ['category' => 'billing', 'confidence' => 0.86];
        }
        if (preg_match('/buy|pricing|demo|sales|upgrade|plan/', $text)) {
            return ['category' => 'sales', 'confidence' => 0.8];
        }
        if (preg_match('/help|broken|error|issue|support|bug/', $text)) {
            return ['category' => 'support', 'confidence' => 0.78];
        }

        return ['category' => 'unknown', 'confidence' => 0.4];
    }
}
