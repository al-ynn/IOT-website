<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceAccessAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'access_level' => ['required', 'string', 'in:viewer,full_access'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace(array_intersect_key($this->all(), array_flip(['device_id', 'user_id', 'access_level'])));
    }
}
