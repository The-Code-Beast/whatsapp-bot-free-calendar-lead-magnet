<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    private ?Customer $authorizedCustomer = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->user()?->workspace;

        if ($workspace === null) {
            return false;
        }

        $this->authorizedCustomer = $workspace->customers()->findOrFail($this->route('customer'));

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
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'string', Rule::in(['new', 'lead', 'qualified', 'booked', 'completed', 'lost'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'phone' => ['prohibited'],
            'workspace_id' => ['prohibited'],
            'source' => ['prohibited'],
        ];
    }

    public function customer(): Customer
    {
        return $this->authorizedCustomer;
    }
}
