<?php

namespace App\Http\Controllers;

use App\Services\KeywordClassifier;
use App\Services\RequestsService;
use Illuminate\Http\Request;

class RequestsController extends Controller
{
    public function __construct(
        private readonly RequestsService $requestsService,
        private readonly KeywordClassifier $classifier,
    ) {
    }

    public function list()
    {
        return $this->requestsService->list();
    }

    public function history(Request $request)
    {
        return [
            'items' => [],
            'message' => 'Classification history is not implemented yet.',
        ];
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

    /**
     * Classify a customer request. Business rules currently live in the controller.
     */
    public function classify(Request $request)
    {
        $body = $request->all();
        $message = $body['message'] ?? null;
        if (! $message || ! is_string($message) || strlen(trim($message)) === 0) {
            return ['error' => 'message must be a non-empty string'];
        }

        if (mb_strlen($message) > 2000) {
            return ['error' => 'message too long'];
        }

        $trimmed = trim($message);
        $result = $this->classifier->classify($trimmed);

        // Soften confidence for very short messages.
        if (count(preg_split('/\s+/', $trimmed)) < 3 && $result['category'] !== 'unknown') {
            $result = [
                'category' => $result['category'],
                'confidence' => max(0.5, $result['confidence'] - 0.15),
            ];
        }

        // Prefer "unknown" when confidence is weak.
        if ($result['confidence'] < 0.55) {
            $result = ['category' => 'unknown', 'confidence' => $result['confidence']];
        }

        $requestId = $body['requestId'] ?? null;
        if ($requestId) {
            $existing = $this->requestsService->getById($requestId);
            $existing->category = $result['category'];
            $existing->confidence = $result['confidence'];
            if ($existing->status === 'open') {
                $existing->status = 'in_progress';
            }
            $this->requestsService->save($existing);
        }

        return [
            'category' => $result['category'],
            'confidence' => $result['confidence'],
            'requestId' => $requestId ?? null,
        ];
    }
}
