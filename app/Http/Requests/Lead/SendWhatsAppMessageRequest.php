<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class SendWhatsAppMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:4096',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'content' => is_string($this->content) ? trim($this->content) : $this->content,
        ]);
    }
}
