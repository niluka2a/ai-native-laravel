<?php

namespace App\Http\Resources;

use App\Data\ClassificationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClassificationResult
 */
class ClassificationResource extends JsonResource
{
    public function __construct(ClassificationResult $result, private readonly ?string $requestId)
    {
        parent::__construct($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'category' => $this->category->value,
            'confidence' => $this->confidence,
            'requestId' => $this->requestId,
        ];
    }
}
