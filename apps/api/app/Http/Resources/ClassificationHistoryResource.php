<?php

namespace App\Http\Resources;

use App\Models\RequestClassification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestClassification
 */
class ClassificationHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requestId' => $this->request_id,
            'requestMessage' => $this->request->message,
            'requestStatus' => $this->request->status->value,
            'category' => $this->category->value,
            'confidence' => $this->confidence,
            'provider' => $this->provider,
            'createdAt' => $this->created_at,
        ];
    }
}
