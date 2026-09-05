<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceAccessAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'access_level' => ['required', 'string', 'in:viewer,full_access'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace(array_intersect_key($this->all(), array_flip(['access_level'])));
    }
}
