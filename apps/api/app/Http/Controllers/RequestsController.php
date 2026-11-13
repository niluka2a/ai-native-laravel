<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassifyRequest;
use App\Http\Requests\HistoryRequest;
use App\Http\Resources\ClassificationHistoryResource;
use App\Services\ClassificationService;
use App\Services\RequestsService;
use Illuminate\Http\Request;

class RequestsController extends Controller
{
    public function __construct(
        private readonly RequestsService $requestsService,
        private readonly ClassificationService $classification,
    ) {}

    public function list()
    {
        return $this->requestsService->list();
    }

    public function history(HistoryRequest $request)
    {
        return ClassificationHistoryResource::collection(
            $this->classification->history($request->category()),
        );
    }

    public function show(string $id)
    {
        return $this->requestsService->getById($id);
    }

    public function store(Request $request)
    {
        $message = $request->input('message');
        if (! $message || ! is_string($message)) {
            return ['error' => 'message is required'];
        }

        return $this->requestsService->create($message);
    }

    public function updateStatus(Request $request, string $id)
    {
        $status = $request->input('status');
        if (! $status) {
            return ['error' => 'status is required'];
        }

        return $this->requestsService->updateStatus($id, $status);
    }

    public function classify(ClassifyRequest $request): array
    {
        $requestId = $request->validated('requestId');
        $result = $this->classification->classifyAndStore($request->validated('message'), $requestId);

        return [
            'category' => $result->category->value,
            'confidence' => $result->confidence,
            'requestId' => $requestId,
        ];
    }
}
