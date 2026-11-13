<?php

namespace App\Contracts;

use App\Data\ClassificationResult;

/**
 * Produces a raw classification for a message. The confidence policy (softening,
 * unknown threshold), storage and history stay in ClassificationService.
 */
interface ClassifierProvider
{
    public function classify(string $message): ClassificationResult;

    /** Short identifier stored with every history row, for example "keyword". */
    public function name(): string;
}
