<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    /**
     * Authorization
     */
    public function authorize(): bool
    {
        return true; // handled by AuthMiddleware
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',

            'phone' => 'sometimes|nullable|string|max:20',

            'email' => 'sometimes|nullable|email|max:255',

            'source' => 'sometimes|nullable|string|max:50',

            'status' => 'sometimes|string|in:new,contacted,qualified,lost,won',

            'assigned_to' => 'sometimes|nullable|uuid',

            'metadata' => 'sometimes|array',
        ];
    }

    /**
     * Clean / normalize input
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'email' => $this->email ? strtolower(trim($this->email)) : null,
            'phone' => PhoneNumber::normalize($this->phone),
        ]);
    }

    /**
     * Custom messages
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status value',
            'email.email' => 'Invalid email format',
        ];
    }
}
