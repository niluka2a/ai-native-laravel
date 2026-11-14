<?php

namespace App\Http\Resources;

use App\Models\CustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer request. Note fields appear only when the caller loaded them.
 *
 * @mixin CustomerRequest
 */
class CustomerRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'category' => $this->category,
            'confidence' => $this->confidence,
            'noteCount' => $this->whenCounted('notes'),
            'latestNotePreview' => $this->whenLoaded('latestNote', fn () => $this->latestNote?->body),
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
            'notes' => RequestNoteResource::collection($this->whenLoaded('notes')),
        ];
    }
}
