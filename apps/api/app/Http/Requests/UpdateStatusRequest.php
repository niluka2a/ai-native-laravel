<?php

namespace App\Http\Requests;

use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(RequestStatus::class)],
        ];
    }

    public function status(): RequestStatus
    {
        return $this->enum('status', RequestStatus::class);
    }
}
