<?php

namespace App\Models;

use App\Enums\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only log of classifications stored on a request.
 *
 * @property int $id
 * @property string $request_id
 * @property Category $category
 * @property float $confidence
 * @property string $provider
 * @property Carbon $created_at
 */
class RequestClassification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['request_id', 'category', 'confidence', 'provider'];

    protected function casts(): array
    {
        return [
            'category' => Category::class,
            'confidence' => 'float',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    /**
     * Limit to one category; does nothing when null.
     */
    public function scopeOfCategory(Builder $query, ?Category $category): void
    {
        $query->when($category, fn (Builder $q) => $q->where('category', $category->value));
    }
}
