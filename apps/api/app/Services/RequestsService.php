<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\CustomerRequest;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestsService
{
    /**
     * Requests newest first, with their note count and latest note eager loaded.
     *
     * @return Collection<int, CustomerRequest>
     */
    public function list(): Collection
    {
        return CustomerRequest::query()
            ->withCount('notes')
            ->with('latestNote')
            ->orderByDesc('customer_requests.created_at')
            ->get();
    }

    public function getById(string $id): CustomerRequest
    {
        $row = CustomerRequest::query()->with('notes')->find($id);
        if (! $row) {
            throw new NotFoundHttpException("Request {$id} not found");
        }

        return $row;
    }

    public function updateStatus(string $id, RequestStatus $status): CustomerRequest
    {
        $row = CustomerRequest::query()->findOrFail($id);
        $row->status = $status;
        $row->save();

        return $row;
    }

    public function create(string $message): CustomerRequest
    {
        return CustomerRequest::create([
            'message' => $message,
            'status' => RequestStatus::Open,
            'category' => null,
            'confidence' => null,
        ]);
    }
}
