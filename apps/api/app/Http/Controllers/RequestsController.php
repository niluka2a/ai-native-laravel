<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassifyRequest;
use App\Http\Requests\HistoryRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\ClassificationHistoryResource;
use App\Http\Resources\ClassificationResource;
use App\Http\Resources\CustomerRequestResource;
use App\Services\ClassificationService;
use App\Services\RequestsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequestsController extends Controller
{
    public function __construct(
        private readonly RequestsService $requestsService,
        private readonly ClassificationService $classification,
    ) {}

    public function list(): AnonymousResourceCollection
    {
        return CustomerRequestResource::collection($this->requestsService->list());
    }

    public function history(HistoryRequest $request): AnonymousResourceCollection
    {
        return ClassificationHistoryResource::collection(
            $this->classification->history($request->category()),
        );
    }

    public function show(string $id): CustomerRequestResource
    {
        return new CustomerRequestResource($this->requestsService->getById($id));
    }

    public function store(StoreCustomerRequest $request): CustomerRequestResource
    {
        return new CustomerRequestResource($this->requestsService->create($request->validated('message')));
    }

    public function updateStatus(UpdateStatusRequest $request, string $id): CustomerRequestResource
    {
        return new CustomerRequestResource(
            $this->requestsService->updateStatus($id, $request->status()),
        );
    }

    public function classify(ClassifyRequest $request): ClassificationResource
    {
        $requestId = $request->validated('requestId');
        $result = $this->classification->classifyAndStore($request->validated('message'), $requestId);

        return new ClassificationResource($result, $requestId);
    }
}
