<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantAutomationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'auto_assignment_enabled' => 'required|boolean',
            'assignment_strategy' => ['required', 'string', Rule::in(['least_load', 'round_robin'])],
            'auto_reply_enabled' => 'required|boolean',
            'auto_reply_template' => 'nullable|string|max:2000',
            'follow_up_threshold_minutes' => 'required|integer|min:5|max:10080',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'assignment_strategy' => is_string($this->assignment_strategy) ? trim($this->assignment_strategy) : $this->assignment_strategy,
            'auto_reply_template' => is_string($this->auto_reply_template) ? trim($this->auto_reply_template) : $this->auto_reply_template,
        ]);
    }
}
