<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestNote extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'request_notes';

    protected $fillable = ['request_id', 'body', 'author_name'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }
}
