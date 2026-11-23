<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => (string) $this->id,
            'name'        => $this->name,
            'username'    => $this->username,
            'email'       => $this->email ?? '',
            'role'        => $this->role,
            'isActive'    => $this->is_active,
            'initials'    => $this->initials,
            'lastLoginAt' => $this->last_login_at?->toISOString(),
            'createdAt'   => $this->created_at?->toISOString(),
        ];
    }
}