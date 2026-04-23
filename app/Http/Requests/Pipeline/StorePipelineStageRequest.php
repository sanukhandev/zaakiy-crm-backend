<?php

namespace App\Http\Requests\Pipeline;

use Illuminate\Foundation\Http\FormRequest;

class StorePipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'order_index' => 'nullable|integer|min:0',
        ];
    }
}
