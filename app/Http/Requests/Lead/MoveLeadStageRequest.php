<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class MoveLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage_id' => 'required|uuid',
            'position' => 'required|integer|min:0',
        ];
    }
}
