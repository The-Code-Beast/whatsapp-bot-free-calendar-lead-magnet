<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'appointment_duration_minutes' => ['required', 'integer', Rule::in([15, 20, 30, 45, 60, 90, 120])],
            'buffer_minutes' => ['required', 'integer', Rule::in([0, 5, 10, 15, 30, 60])],
            'bot_tone' => ['required', 'string', Rule::in(['friendly', 'professional', 'concise', 'warm'])],
            'assistant_language' => ['required', 'string', Rule::in(['auto', 'en', 'es'])],
            'bot_instructions' => ['nullable', 'string', 'max:5000'],
            'business_hours' => ['required', 'array', 'size:7'],
            'business_hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'business_hours.*.is_enabled' => ['required', 'boolean'],
            'business_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'business_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->array('business_hours') as $index => $hours) {
                    if (! ($hours['is_enabled'] ?? false)) {
                        continue;
                    }

                    if (blank($hours['opens_at'] ?? null) || blank($hours['closes_at'] ?? null)) {
                        $validator->errors()->add("business_hours.{$index}.opens_at", 'Opening and closing times are required for enabled days.');

                        continue;
                    }

                    if ($hours['closes_at'] <= $hours['opens_at']) {
                        $validator->errors()->add("business_hours.{$index}.closes_at", __('messages.closing_after_opening'));
                    }
                }
            },
        ];
    }
}
