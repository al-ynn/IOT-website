<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['staff', 'admin'])],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ];
    }
}
