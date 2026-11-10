<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestNote;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestsService
{
    /**
     * @return array<int, array{id: string, message: string, status: string, category: string|null, confidence: float|null, noteCount: int, latestNotePreview: string|null, createdAt: string, updatedAt: string}>
     */
    public function list(): array
    {
        $rows = CustomerRequest::query()
            ->orderByDesc('created_at')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $notes = RequestNote::query()
                ->where('request_id', $row->id)
                ->orderByDesc('created_at')
                ->get();
            $row->setRelation('notes', $notes);

            $items[] = [
                'id' => $row->id,
                'message' => $row->message,
                'status' => $row->status,
                'category' => $row->category,
                'confidence' => $row->confidence,
                'noteCount' => $notes->count(),
                'latestNotePreview' => $notes->first()?->body,
                'createdAt' => $row->created_at->toISOString(),
                'updatedAt' => $row->updated_at->toISOString(),
            ];
        }

        return $items;
    }

    public function getById(string $id): CustomerRequest
    {
        $row = CustomerRequest::query()->with('notes')->find($id);
        if (! $row) {
            throw new NotFoundHttpException("Request {$id} not found");
        }

        return $row;
    }

    public function updateStatus(string $id, string $status): CustomerRequest
    {
        $row = $this->getById($id);
        $row->status = $status;
        $row->save();

        return $row;
    }

    public function create(string $message): CustomerRequest
    {
        return CustomerRequest::create([
            'message' => $message,
            'status' => 'open',
            'category' => null,
            'confidence' => null,
        ]);
    }

    public function save(CustomerRequest $request): CustomerRequest
    {
        $request->save();

        return $request;
    }
}
