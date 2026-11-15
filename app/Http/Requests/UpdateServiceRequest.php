<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    private ?Service $authorizedService = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->user()?->workspace;
        abort_if($workspace === null, 404);

        $this->authorizedService = $workspace->services()->find($this->route('service'));
        abort_if($this->authorizedService === null, 404);

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
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('services', 'name')
                    ->where('workspace_id', $this->user()?->workspace?->id)
                    ->ignore($this->authorizedService),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'between:5,480'],
            'price' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,999999.99'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,10000'],
            'workspace_id' => ['prohibited'],
            'price_cents' => ['prohibited'],
        ];
    }

    public function service(): Service
    {
        return $this->authorizedService;
    }
}
