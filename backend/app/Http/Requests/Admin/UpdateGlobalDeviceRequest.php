<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:100'],
            'protocol' => ['sometimes', 'required', 'string', 'max:50'],
            'location_id' => ['sometimes', 'nullable', 'integer'], 'location' => ['prohibited'],
            'macAddress' => ['sometimes', 'nullable', 'string', 'max:50'],
            'baseRevisionId' => ['sometimes', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace(array_intersect_key($this->all(), array_flip([
            'name', 'type', 'protocol', 'location_id', 'macAddress', 'baseRevisionId',
        ])));
    }
}
