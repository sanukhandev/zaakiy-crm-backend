<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantWhatsAppIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_account_id' => 'nullable|string|max:255',
            'phone_number_id' => 'required|string|max:255',
            'access_token' => 'nullable|string|max:4096',
            'sender_label' => 'nullable|string|max:80',
            'base_url' => 'nullable|url|max:255',
            'api_version' => 'nullable|string|max:16',
            'is_active' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'business_account_id' => is_string($this->business_account_id) ? trim($this->business_account_id) : $this->business_account_id,
            'phone_number_id' => is_string($this->phone_number_id) ? trim($this->phone_number_id) : $this->phone_number_id,
            'access_token' => is_string($this->access_token) ? trim($this->access_token) : $this->access_token,
            'sender_label' => is_string($this->sender_label) ? trim($this->sender_label) : $this->sender_label,
            'base_url' => is_string($this->base_url) ? trim($this->base_url) : $this->base_url,
            'api_version' => is_string($this->api_version) ? trim($this->api_version) : $this->api_version,
        ]);
    }
}
