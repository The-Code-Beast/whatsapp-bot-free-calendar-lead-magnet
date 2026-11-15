<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAvailabilityRequest extends FormRequest
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
            'buffer_minutes' => ['required', 'integer', Rule::in([0, 5, 10, 15, 30, 60])],
            'business_hours' => ['required', 'array', 'size:7'],
            'business_hours.*' => ['required', 'array:day_of_week,is_enabled,opens_at,closes_at'],
            'business_hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'business_hours.*.is_enabled' => ['required', 'boolean'],
            'business_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'business_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
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
        }];
    }
}
