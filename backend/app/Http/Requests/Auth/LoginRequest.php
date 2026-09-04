<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'organization_id' => ['prohibited'],
            'organizationId' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'tenantId' => ['prohibited'],
            'role' => ['prohibited'],
            'platform_role' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
