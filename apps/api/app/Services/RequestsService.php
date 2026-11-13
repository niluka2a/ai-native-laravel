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
            ->withCount('notes')
            ->addSelect(['latest_note_preview' => RequestNote::query()
                ->select('body')
                ->whereColumn('request_notes.request_id', 'customer_requests.id')
                ->orderByDesc('request_notes.created_at')
                ->orderByDesc('request_notes.id')
                ->limit(1)])
            ->orderByDesc('customer_requests.created_at')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => $row->id,
                'message' => $row->message,
                'status' => $row->status,
                'category' => $row->category,
                'confidence' => $row->confidence,
                'noteCount' => (int) $row->notes_count,
                'latestNotePreview' => $row->latest_note_preview,
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
