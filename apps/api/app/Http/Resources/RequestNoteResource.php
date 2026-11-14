<?php

namespace App\Http\Resources;

use App\Models\RequestNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestNote
 */
class RequestNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'authorName' => $this->author_name,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
