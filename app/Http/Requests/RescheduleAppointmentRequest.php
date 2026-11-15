<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
{
    private ?Appointment $authorizedAppointment = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->user()?->workspace;

        if ($workspace === null) {
            return false;
        }

        $this->authorizedAppointment = $workspace->appointments()
            ->where('public_id', $this->route('appointment'))
            ->firstOrFail();

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
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'status' => ['prohibited'],
            'workspace_id' => ['prohibited'],
        ];
    }

    public function appointment(): Appointment
    {
        return $this->authorizedAppointment;
    }
}
