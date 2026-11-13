<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $message
 * @property string $status open | in_progress | resolved
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
            'confidence' => 'float',
        ];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(RequestNote::class, 'request_id');
    }

    public function latestNote(): HasOne
    {
        return $this->hasOne(RequestNote::class, 'request_id')->latestOfMany('created_at');
    }
}
