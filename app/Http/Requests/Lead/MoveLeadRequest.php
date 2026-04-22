<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class MoveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:new,contacted,qualified,won,lost',
            'position' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status value',
        ];
    }
}
