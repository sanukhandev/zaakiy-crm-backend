<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class BulkLeadDeleteRequest extends FormRequest
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
        ];
    }
}
