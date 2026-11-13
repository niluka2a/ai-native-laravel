<?php

namespace App\Services;

use App\Contracts\ClassifierProvider;
use App\Data\ClassificationResult;
use App\Enums\Category;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class ClassificationService
{
    /** Messages with fewer words than this get a softened confidence. */
    private const SHORT_MESSAGE_MAX_WORDS = 3;

    private const SHORT_MESSAGE_PENALTY = 0.15;

    private const SOFTENED_FLOOR = 0.5;

    /** Results below this confidence fall back to "unknown". */
    private const UNKNOWN_THRESHOLD = 0.55;

    public function __construct(private readonly ClassifierProvider $classifier) {}

    /**
     * Classify a message and apply the confidence policy. Pure, no DB access.
     */
    public function classify(string $message): ClassificationResult
    {
        $message = trim($message);
        $result = $this->classifier->classify($message);

        // Soften confidence for very short messages. The limit stops splitting
        // once we know the message is long enough.
        if (
            count(preg_split('/\s+/', $message, self::SHORT_MESSAGE_MAX_WORDS)) < self::SHORT_MESSAGE_MAX_WORDS
            && $result->category !== Category::Unknown
        ) {
            $result = new ClassificationResult(
                $result->category,
                max(self::SOFTENED_FLOOR, $result->confidence - self::SHORT_MESSAGE_PENALTY),
            );
        }

        // Prefer "unknown" when confidence is weak.
        if ($result->confidence < self::UNKNOWN_THRESHOLD) {
            $result = new ClassificationResult(Category::Unknown, $result->confidence);
        }

        return $result;
    }

    /**
     * Classify a message and, when a request id is given, store the result on that request
     * and append it to the history, in one transaction.
     */
    public function classifyAndStore(string $message, ?string $requestId): ClassificationResult
    {
        $result = $this->classify($message);

        if ($requestId !== null) {
            DB::transaction(function () use ($requestId, $result) {
                $request = CustomerRequest::query()->findOrFail($requestId);
                $request->applyClassification($result);
                $request->save();

                $request->classifications()->create([
                    'category' => $result->category,
                    'confidence' => $result->confidence,
                    'provider' => $this->classifier->name(),
                ]);
            });
        }

        return $result;
    }

    /**
     * Stored classifications, newest first, optionally limited to one category.
     */
    public function history(?Category $category, int $perPage = 50): Paginator
    {
        return RequestClassification::query()
            ->with('request:id,message,status')
            ->ofCategory($category)
            ->latest()
            ->orderByDesc('id')
            ->simplePaginate($perPage)
            ->withQueryString();
    }
}
