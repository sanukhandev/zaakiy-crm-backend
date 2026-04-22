<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class BulkLeadUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_ids' => 'required|array|min:1|max:100',
            'lead_ids.*' => 'required|uuid',
            'status' => 'sometimes|string|in:new,contacted,qualified,won,lost',
            'source' => 'sometimes|nullable|string|max:50',
            'assigned_to' => 'sometimes|nullable|uuid',
            'metadata' => 'sometimes|array',
        ];
    }
}
