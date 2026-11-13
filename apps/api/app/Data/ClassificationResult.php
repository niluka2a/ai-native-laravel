<?php

namespace App\Data;

use App\Enums\Category;

final readonly class ClassificationResult
{
    public function __construct(
        public Category $category,
        public float $confidence,
    ) {}
}
