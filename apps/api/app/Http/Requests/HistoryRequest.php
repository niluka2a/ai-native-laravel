<?php

namespace App\Http\Requests;

use App\Enums\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoryRequest extends FormRequest
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
            'category' => ['nullable', Rule::enum(Category::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function category(): ?Category
    {
        return $this->enum('category', Category::class);
    }
}
