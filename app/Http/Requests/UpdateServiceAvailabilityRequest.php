<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateServiceAvailabilityRequest extends FormRequest
{
    private ?Service $authorizedService = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->user()?->workspace;

        if ($workspace === null) {
            return false;
        }

        $this->authorizedService = $workspace->services()->findOrFail($this->route('service'));

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uses_custom_hours' => ['required', 'boolean'],
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
            if (! $this->boolean('uses_custom_hours')) {
                return;
            }

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

    public function service(): Service
    {
        return $this->authorizedService;
    }
}
