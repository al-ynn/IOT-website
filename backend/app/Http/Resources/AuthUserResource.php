<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuthUserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'organizationId' => $this->organization_id ? (string) $this->organization_id : null,
            // These legacy values are presentation compatibility only. Backend
            // authorization resolves the product role through User::productRole().
            'role' => $this->role,
            'platformRole' => $this->platform_role,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
