<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWhatsAppConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->workspace !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'waba_id' => ['nullable', 'string', 'regex:/^\d+$/', 'max:40'],
            'phone_number_id' => [
                'required',
                'string',
                'regex:/^\d+$/',
                'max:40',
                Rule::unique('whats_app_connections', 'phone_number_id')
                    ->ignore($this->user()?->workspace?->whatsAppConnection?->id),
            ],
            'access_token' => ['nullable', 'string', 'max:4096'],
            'bot_enabled' => ['required', 'boolean'],
            'workspace_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (blank($this->input('access_token')) && $this->user()?->workspace?->whatsAppConnection === null) {
                    $validator->errors()->add('access_token', 'An access token is required to connect WhatsApp.');
                }

                if (blank(config('services.whatsapp.verify_token')) || blank(config('services.whatsapp.app_secret'))) {
                    $validator->errors()->add('access_token', __('messages.configure_meta'));
                }
            },
        ];
    }
}
