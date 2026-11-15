<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutomationSettingsRequest extends FormRequest
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
            'reminder_enabled' => ['required', 'boolean'],
            'reminder_hours_before' => ['required', 'integer', Rule::in([1, 2, 4, 12, 24, 48, 72])],
            'reminder_message' => ['nullable', 'string', 'max:1000'],
            'followup_enabled' => ['required', 'boolean'],
            'followup_hours_after' => ['required', 'integer', Rule::in([0, 1, 2, 4, 24, 48])],
            'followup_message' => ['nullable', 'string', 'max:1000'],
            'workspace_id' => ['prohibited'],
        ];
    }
}
