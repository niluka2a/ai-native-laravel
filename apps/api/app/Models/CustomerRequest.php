<?php

namespace App\Models;

use App\Data\ClassificationResult;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $message
 * @property RequestStatus $status
 * @property string|null $category
 * @property float|null $confidence
 */
class CustomerRequest extends Model
{
    use HasUuids;

    protected $table = 'customer_requests';

    protected $fillable = ['message', 'status', 'category', 'confidence'];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'confidence' => 'float',
        ];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(RequestNote::class, 'request_id');
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(RequestClassification::class, 'request_id');
    }

    public function latestNote(): HasOne
    {
        return $this->hasOne(RequestNote::class, 'request_id')->latestOfMany('created_at');
    }

    /**
     * Store a classification result and move an open request to in_progress.
     */
    public function applyClassification(ClassificationResult $result): void
    {
        $this->category = $result->category->value;
        $this->confidence = $result->confidence;

        if ($this->status === RequestStatus::Open) {
            $this->status = RequestStatus::InProgress;
        }
    }
}
