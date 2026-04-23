<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'source' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:new,contacted,qualified,lost,won',
            'assigned_to' => 'nullable|uuid',
            'metadata' => 'nullable|array',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'email' => $this->email ? strtolower(trim($this->email)) : null,
            'phone' => PhoneNumber::normalize($this->phone),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Lead name is required',
            'status.in' => 'Invalid status value',
            'email.email' => 'Invalid email format',
        ];
    }
}
